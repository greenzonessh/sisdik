<?php

namespace Langgas\SisdikBundle\Controller;

use Doctrine\ORM\EntityManager;
use Langgas\SisdikBundle\Entity\DaftarBiayaSekali;
use Langgas\SisdikBundle\Entity\PembayaranSekali;
use Langgas\SisdikBundle\Entity\Sekolah;
use Langgas\SisdikBundle\Entity\Siswa;
use Langgas\SisdikBundle\Entity\TransaksiPembayaranSekali;
use Langgas\SisdikBundle\Util\EscapeCommand;
use PHPPdf\Core\Facade;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use JMS\SecurityExtraBundle\Annotation\PreAuthorize;

/**
 * @Route("/cetak-pembayaran-biaya-sekali-bayar/{sid}")
 * @PreAuthorize("hasAnyRole('ROLE_BENDAHARA', 'ROLE_KASIR')")
 */
class PembayaranBiayaSekaliCetakController extends Controller
{
    const RECEIPTS_DIR = "/receipts/";

    /**
     * Cetak nota pembayaran biaya sekali bayar
     *
     * @Route("/{pid}/{id}", name="pembayaran_biaya_sekali__cetaknota")
     */
    public function printReceiptAction(Request $request, $sid, $pid, $id)
    {
        $sekolah = $this->getSekolah();

        /* @var $em EntityManager */
        $em = $this->getDoctrine()->getManager();

        $siswa = $em->getRepository('LanggasSisdikBundle:Siswa')->find($sid);
        if (!(is_object($siswa) && $siswa instanceof Siswa)) {
            throw $this->createNotFoundException('Entity Siswa tak ditemukan.');
        }

        $pembayaran = $em->getRepository('LanggasSisdikBundle:PembayaranSekali')->find($pid);
        if (!(is_object($pembayaran) && $pembayaran instanceof PembayaranSekali)) {
            throw $this->createNotFoundException('Entity PembayaranSekali tak ditemukan.');
        }
        $daftarBiayaSekali = $pembayaran->getDaftarBiayaSekali();

        $transaksi = $em->getRepository('LanggasSisdikBundle:TransaksiPembayaranSekali')->find($id);
        if (!$transaksi && !($transaksi instanceof TransaksiPembayaranSekali)) {
            throw $this->createNotFoundException('Entity TransaksiPembayaranSekali tak ditemukan.');
        }

        $transaksiPembayaran = $em->getRepository('LanggasSisdikBundle:TransaksiPembayaranSekali')
            ->findBy([
                'pembayaranSekali' => $pid,
            ], [
                'waktuSimpan' => 'ASC',
            ])
        ;

        $counterTransaksi = 0;
        $nomorCicilan = 1;
        $nomorTransaksi = [];
        foreach ($transaksiPembayaran as $t) {
            if ($t instanceof TransaksiPembayaranSekali) {
                $counterTransaksi++;
                $nomorTransaksi[$t->getNomorTransaksi()] = $t->getNomorTransaksi();
                if ($t->getId() == $id) {
                    $nomorCicilan = $counterTransaksi;
                    break;
                }
            }
        }

        $nomorCicilan = count($transaksiPembayaran) <= 1 ? 1 : $nomorCicilan;
        if (count($daftarBiayaSekali) > 1) {
            $adaCicilan = false;
        } else {
            if ($transaksiPembayaran[0]->getNominalPembayaran() == $pembayaran->getTotalNominalTransaksiPembayaranSekali() && count($transaksiPembayaran) == 1) {
                $hargaItem = 0;
                foreach ($daftarBiayaSekali as $biaya) {
                    if ($biaya instanceof DaftarBiayaSekali) {
                        $hargaItem = $biaya->getNominal();
                    }
                }

                if ($pembayaran->getAdaPotongan()) {
                    $hargaItem = $hargaItem - ($pembayaran->getNominalPotongan() + $pembayaran->getPersenPotonganDinominalkan());
                }

                if ($hargaItem > $pembayaran->getTotalNominalTransaksiPembayaranSekali()) {
                    $adaCicilan = true;
                } else {
                    $adaCicilan = false;
                }
            } else {
                $adaCicilan = true;
            }
        }
        $totalPembayaranHinggaTransaksiTerpilih = $pembayaran->getTotalNominalTransaksiPembayaranSekaliHinggaTransaksiTerpilih($nomorTransaksi);

        $tahun = $transaksi->getWaktuSimpan()->format('Y');
        $bulan = $transaksi->getWaktuSimpan()->format('m');

        /* @var $translator Translator */
        $translator = $this->get('translator');
        $formatter = new \NumberFormatter($this->container->getParameter('locale'), \NumberFormatter::CURRENCY);
        $symbol = $formatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

        $output = 'pdf';
        $pilihanCetak = $em->getRepository('LanggasSisdikBundle:PilihanCetakKwitansi')
            ->findBy([
                'sekolah' => $sekolah,
            ])
        ;
        foreach ($pilihanCetak as $pilihan) {
            $output = $pilihan->getOutput();
        }

        $fs = new Filesystem();
        $schoolReceiptDir = $this->get('kernel')->getRootDir() . self::RECEIPTS_DIR . $sekolah->getId();
        if (!$fs->exists($schoolReceiptDir . DIRECTORY_SEPARATOR . $tahun . DIRECTORY_SEPARATOR . $bulan)) {
            $fs->mkdir($schoolReceiptDir . DIRECTORY_SEPARATOR . $tahun . DIRECTORY_SEPARATOR . $bulan);
        }

        if ($output == 'esc_p') {
            $filetarget = $transaksi->getNomorTransaksi() . ".sisdik.direct";
            $documenttarget = $schoolReceiptDir . DIRECTORY_SEPARATOR . $tahun . DIRECTORY_SEPARATOR . $bulan . DIRECTORY_SEPARATOR . $filetarget;

            $commands = new EscapeCommand();
            $commands->addLineSpacing_1_6();
            $commands->addPageLength33Lines();
            $commands->addMarginBottom5Lines();
            $commands->addMaster10CPI();
            $commands->addMasterCondensed();
            $commands->addModeDraft();

            // max 137 characters
            $maxwidth = 137;
            $labelwidth1 = 20;
            $labelwidth2 = 15;
            $marginBadan = 7;
            $maxwidth2 = $maxwidth - $marginBadan;
            $spasi = "";

            $commands->addContent($sekolah->getNama() . "\r\n");
            $commands->addContent($sekolah->getAlamat() . ", " . $sekolah->getKodepos() . "\r\n");

            $phonefaxline = $sekolah->getTelepon() != "" ? $translator->trans('telephone', [], 'printing') . " " . $sekolah->getTelepon() : "";
            $phonefaxline .=
                $sekolah->getFax() != "" ?
                    (
                        $phonefaxline != "" ?
                            ", " . $translator->trans('faximile', [], 'printing') . " " . $sekolah->getFax()
                            : $translator->trans('faximile', [], 'printing') . " " . $sekolah->getFax()
                    )
                    : ""
            ;

            $commands->addContent($phonefaxline . "\r\n");

            $commands->addContent(str_repeat("=", $maxwidth) . "\r\n");
            $commands->addContent("\r\n");

            $nomorkwitansi = $translator->trans('receiptnum', [], 'printing');
            $spasi = str_repeat(" ", ($labelwidth2 - strlen($nomorkwitansi)));
            $barisNomorkwitansi = $nomorkwitansi . $spasi . ": " . $transaksi->getNomorTransaksi();

            $namasiswa = $translator->trans('nama.siswa', [], 'printing');
            $spasi = str_repeat(" ", ($labelwidth1 - strlen($namasiswa)));
            $barisNamasiswa = $namasiswa . $spasi . ": " . $siswa->getNamaLengkap();

            $tanggal = $translator->trans('date', [], 'printing');
            $spasi = str_repeat(" ", ($labelwidth2 - strlen($tanggal)));
            $dateFormatter = $this->get('bcc_extra_tools.date_formatter');
            $barisTanggal = $tanggal . $spasi . ": " . $dateFormatter->format($transaksi->getWaktuSimpan(), 'long');

            $nomorIdentitas = $translator->trans('identitas.sisdik', [], 'printing') . ' / ' . $translator->trans('nomor.induk', [], 'printing');
            $spasi = str_repeat(" ", ($labelwidth1 - strlen($nomorIdentitas)));
            $barisNomorIdentitas = $nomorIdentitas . $spasi . ": " . $siswa->getNomorIndukSistem() . ' / ' . $siswa->getNomorInduk();

            $pengisiBaris1 = strlen($barisNomorkwitansi);
            $pengisiBaris2 = strlen($barisTanggal);
            $pengisiBarisTerbesar = $pengisiBaris1 > $pengisiBaris2 ? $pengisiBaris1 : $pengisiBaris2;

            $sisaBaris1 = $maxwidth2 - strlen($barisNamasiswa) - $pengisiBarisTerbesar;
            $sisaBaris2 = $maxwidth2 - strlen($barisNomorIdentitas) - $pengisiBarisTerbesar;

            $commands->addContent(str_repeat(" ", $marginBadan) . $barisNamasiswa . str_repeat(" ", $sisaBaris1) . $barisNomorkwitansi . "\r\n");
            $commands->addContent(str_repeat(" ", $marginBadan) . $barisNomorIdentitas . str_repeat(" ", $sisaBaris2) . $barisTanggal . "\r\n");
            $commands->addContent("\r\n");

            $twoPages = false;
            if ($adaCicilan) {
                /****** kwitansi format cicilan: formular ******/

                $commands->addContent("\r\n");

                $labelwidth3 = 25;
                $symbolwidth = count($symbol);
                $pricewidth = 15;
                $lebarketerangan = 93;

                $namaItemPembayaran = "";
                $nominalHargaItemPembayaran = 0;
                foreach ($daftarBiayaSekali as $biaya) {
                    if ($biaya instanceof DaftarBiayaSekali) {
                        $namaItemPembayaran = $biaya->getNama();
                        $nominalHargaItemPembayaran = $biaya->getNominal();
                    }
                }

                $labelItemPembayaran = $translator->trans('paymentitem', [], 'printing');
                $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelItemPembayaran)));
                $barisItemPembayaran = $labelItemPembayaran . $spasi . ": " . $namaItemPembayaran;
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisItemPembayaran . "\r\n");

                $labelHargaItemPembayaran = $translator->trans('itemprice', [], 'printing');
                $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelHargaItemPembayaran)));
                $valueHargaItemPembayaran = number_format($nominalHargaItemPembayaran, 0, ',', '.');
                $spasi2 = str_repeat(" ", $pricewidth - (strlen($valueHargaItemPembayaran)));
                $barisHargaItemPembayaran = $labelHargaItemPembayaran . $spasi . ": " . $symbol . $spasi2 . $valueHargaItemPembayaran;
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisHargaItemPembayaran . "\r\n");

                $nominalPotongan = 0;
                if ($pembayaran->getAdaPotongan()) {
                    $labelPotongan = $translator->trans('discount', [], 'printing');
                    $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelPotongan)));
                    $persenPotongan = "";
                    if ($pembayaran->getJenisPotongan() == 'nominal') {
                        $nominalPotongan = $pembayaran->getNominalPotongan();
                        $valuePotongan = number_format($pembayaran->getNominalPotongan(), 0, ',', '.');
                    } else {
                        $nominalPotongan = $pembayaran->getPersenPotonganDinominalkan();
                        $valuePotongan = number_format($pembayaran->getPersenPotonganDinominalkan(), 0, ',', '.');
                        $persenPotongan = " (" . $pembayaran->getPersenPotongan() . "%)";
                    }
                    $spasi2 = str_repeat(" ", $pricewidth - (strlen($valuePotongan)));
                    $barisPotongan = $labelPotongan . $spasi . ": " . $symbol . $spasi2 . $valuePotongan . $persenPotongan;
                    $commands->addContent(str_repeat(" ", $marginBadan) . $barisPotongan . "\r\n");

                    $labelTotalHarga = $translator->trans('totalprice', [], 'printing');
                    $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelTotalHarga)));
                    $valueTotalHarga = number_format($nominalHargaItemPembayaran - $nominalPotongan, 0, ',', '.');
                    $spasi2 = str_repeat(" ", $pricewidth - (strlen($valueTotalHarga)));
                    $barisTotalHarga = $labelTotalHarga . $spasi . ": " . $symbol . $spasi2 . $valueTotalHarga;
                    $commands->addContent(str_repeat(" ", $marginBadan) . $barisTotalHarga . "\r\n");
                }
                $commands->addContent("\r\n");

                $labelPembayaranKe = $translator->trans('paymentnum', [], 'printing');
                $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelPembayaranKe)));
                $barisPembayaranKe = $labelPembayaranKe . $spasi . ": " . $nomorCicilan;
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisPembayaranKe . "\r\n");

                $labelNominalPembayaran = $translator->trans('paymentamount', [], 'printing');
                $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelNominalPembayaran)));
                $valueNominalPembayaran = number_format($transaksi->getNominalPembayaran(), 0, ',', '.');
                $spasi2 = str_repeat(" ", $pricewidth - (strlen($valueNominalPembayaran)));
                $barisNominalPembayaran = $labelNominalPembayaran . $spasi . ": " . $symbol . $spasi2 . $valueNominalPembayaran;
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisNominalPembayaran . "\r\n");

                $labelKeteranganPembayaran = $translator->trans('description', [], 'printing');
                $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelKeteranganPembayaran)));
                $valueKeteranganPembayaran = $transaksi->getKeterangan();
                $valueKeteranganPembayaran = strlen($valueKeteranganPembayaran) > $lebarketerangan ?
                    substr($valueKeteranganPembayaran, 0, ($lebarketerangan - 3)) . "..." : $valueKeteranganPembayaran
                ;
                $barisKeteranganPembayaran = $labelKeteranganPembayaran . $spasi . ": " . $valueKeteranganPembayaran;
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisKeteranganPembayaran . "\r\n");

                $commands->addContent("\r\n");
                $commands->addContent(str_repeat(" ", $marginBadan) . "* * *\r\n");

                $labelTotalSudahBayar = $translator->trans('totalpaidamount', [], 'printing');
                $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelTotalSudahBayar)));
                $valueTotalSudahBayar = number_format($totalPembayaranHinggaTransaksiTerpilih, 0, ',', '.');
                $spasi2 = str_repeat(" ", $pricewidth - (strlen($valueTotalSudahBayar)));
                $barisTotalSudahBayar = $labelTotalSudahBayar . $spasi . ": " . $symbol . $spasi2 . $valueTotalSudahBayar;
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisTotalSudahBayar . "\r\n");

                $labelSisaPembayaran = $translator->trans('unpaidamount', [], 'printing');
                $spasi = str_repeat(" ", ($labelwidth3 - strlen($labelSisaPembayaran)));
                $nominalSisaPembayaran = $nominalHargaItemPembayaran - $nominalPotongan - $totalPembayaranHinggaTransaksiTerpilih;
                if ($nominalSisaPembayaran == 0) {
                    $valueSisaPembayaran = "(" . $translator->trans('settled', [], 'printing') . ")";
                    $barisSisaPembayaran = $labelSisaPembayaran . $spasi . ": " . $valueSisaPembayaran;
                } else {
                    $valueSisaPembayaran = number_format($nominalSisaPembayaran, 0, ',', '.');
                    $spasi2 = str_repeat(" ", $pricewidth - (strlen($valueSisaPembayaran)));
                    $barisSisaPembayaran = $labelSisaPembayaran . $spasi . ": " . $symbol . $spasi2 . $valueSisaPembayaran;
                }
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisSisaPembayaran . "\r\n");

                if (!$pembayaran->getAdaPotongan()) {
                    $commands->addContent("\r\n");
                }
                $commands->addContent("\r\n");
                $commands->addContent("\r\n");

                /****** selesai kwitansi format cicilan ******/
            } else {
                /****** kwitansi format non-cicilan: tabular ******/

                $lebarKolom1 = 5;
                $lebarKolom2 = 70;
                $lebarKolom3 = 23;
                $marginKiriKolom = 1;
                $marginKananKolom = 1;

                $barisGarisTabel = "+"
                    . str_repeat("-", $lebarKolom1) . "+"
                    . str_repeat("-", $lebarKolom2) . "+"
                    . str_repeat("-", $lebarKolom3) . "+"
                ;
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                $kolomNomor = $translator->trans('num', [], 'printing');
                $spasiKolomNomor = $lebarKolom1 - (strlen($kolomNomor) + $marginKiriKolom);
                $barisNamaKolom = "|" . str_repeat(" ", $marginKiriKolom) . $kolomNomor . str_repeat(" ", $spasiKolomNomor);

                $kolomItem = $translator->trans('paymentitem', [], 'printing');
                $spasiKolomItem = $lebarKolom2 - (strlen($kolomItem) + $marginKiriKolom);
                $barisNamaKolom .= "|" . str_repeat(" ", $marginKiriKolom) . $kolomItem . str_repeat(" ", $spasiKolomItem);

                $kolomHarga = $translator->trans('price', [], 'printing') . " ($symbol)";
                $spasiKolomHarga = $lebarKolom3 - (strlen($kolomHarga) + $marginKiriKolom);
                $barisNamaKolom .= "|" . str_repeat(" ", $marginKiriKolom) . $kolomHarga . str_repeat(" ", $spasiKolomHarga) . "|";

                $commands->addContent(str_repeat(" ", $marginBadan) . $barisNamaKolom . "\r\n");
                $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                // uncomment the following line for double page test
                // $daftarBiayaSekali = array_merge($daftarBiayaSekali);

                $maxitemPageone = 9;
                $itemThreshold = 15;
                if ($pembayaran->getAdaPotongan()) {
                    // jumlah item pembayaran maximum dalam 1 halaman: 7
                    // jumlah item pembayaran > 7, buat dua halaman
                    $maxitemPageone = 7;
                    if (count($daftarBiayaSekali) > $maxitemPageone) {
                        $twoPages = true;
                    }
                    // jumlah item pembayaran 8 - 14: 6 item di halaman pertama, 8 - 14 di halaman 2
                    // jumlah item pembayaran >= 15: 13 item di halaman pertama, 14+ item di halaman 2
                } else {
                    if (count($daftarBiayaSekali) > $maxitemPageone) {
                        $twoPages = true;
                    }
                }

                $twoPageFormat = 0;
                if ($twoPages === true && count($daftarBiayaSekali) < $itemThreshold) {
                    $twoPageFormat = 1;
                } elseif ($twoPages === true && count($daftarBiayaSekali) >= $itemThreshold) {
                    $maxitemPageone = 14;
                    $twoPageFormat = 2;
                }

                $num = 1;
                $totalNominalTransaksi = 0;

                foreach ($daftarBiayaSekali as $biaya) {
                    if ($biaya instanceof DaftarBiayaSekali) {
                        $totalNominalTransaksi += $biaya->getNominal();

                        if ($twoPageFormat == 1 && $num == $maxitemPageone) {

                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                            $barisKeteranganBerlanjut = $translator->trans('continue.to.pagetwo', [], 'printing');
                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisKeteranganBerlanjut . "\r\n");

                            if ($pembayaran->getAdaPotongan()) {
                                $commands->addContent("\r\n");
                                $commands->addContent("\r\n");
                            }
                            $commands->addContent("\r\n");
                            $commands->addContent("\r\n");
                            $commands->addContent("\r\n");
                            $commands->addContent("\r\n");
                            $commands->addContent("\r\n");
                            $commands->addContent("\r\n");

                            $labelwidthHal2 = 18;

                            $barisHalaman1 = "(" . $translator->trans('page', [], 'printing') . " 1/2)";
                            $spasiBarisHalaman1 = str_repeat(" ", ($maxwidth2 - strlen($barisHalaman1)));
                            $commands->addContent(str_repeat(" ", $marginBadan) . $spasiBarisHalaman1 . $barisHalaman1 . "\r\n");

                            $nomorKwitansiHal2 = $translator->trans('receiptnum', [], 'printing');
                            $spasi = str_repeat(" ", ($labelwidthHal2 - strlen($nomorKwitansiHal2)));
                            $barisNomorKwitansiHal2 = $nomorKwitansiHal2 . $spasi . ": " . $transaksi->getNomorTransaksi();

                            $nomorIdentitasHal2 = $translator->trans('identitas.sisdik', [], 'printing')
                                . ' / '
                                . $translator->trans('nomor.induk', [], 'printing')
                            ;
                            $spasi = str_repeat(" ", ($labelwidthHal2 - strlen($nomorIdentitasHal2)));
                            $barisNomorIdentitasHal2 = $nomorIdentitasHal2
                                . $spasi
                                . ": "
                                . $transaksi->getPembayaranSekali()->getSiswa()->getNomorIndukSistem()
                                . ' / '
                                . $transaksi->getPembayaranSekali()->getSiswa()->getNomorInduk()
                            ;

                            $pengisiBaris1 = strlen($barisNomorKwitansiHal2);
                            $pengisiBaris2 = strlen($barisNomorIdentitasHal2);
                            $pengisiBarisTerbesar = $pengisiBaris1 > $pengisiBaris2 ? $pengisiBaris1 : $pengisiBaris2;

                            $sisaBaris = $maxwidth2 - $pengisiBarisTerbesar;

                            $commands->addContent(str_repeat(" ", $marginBadan) . str_repeat(" ", $sisaBaris) . $barisNomorKwitansiHal2 . "\r\n");
                            $commands->addContent(str_repeat(" ", $marginBadan) . str_repeat(" ", $sisaBaris) . $barisNomorIdentitasHal2 . "\r\n");

                            $barisKeteranganLanjutan = $translator->trans('continued.from.pageone', [], 'printing');
                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisKeteranganLanjutan . "\r\n");
                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                            $kolomNomor = $translator->trans('num', [], 'printing');
                            $spasiKolomNomor = $lebarKolom1 - (strlen($kolomNomor) + $marginKiriKolom);
                            $barisNamaKolom = "|" . str_repeat(" ", $marginKiriKolom) . $kolomNomor . str_repeat(" ", $spasiKolomNomor);

                            $kolomItem = $translator->trans('paymentitem', [], 'printing');
                            $spasiKolomItem = $lebarKolom2 - (strlen($kolomItem) + $marginKiriKolom);
                            $barisNamaKolom .= "|" . str_repeat(" ", $marginKiriKolom) . $kolomItem . str_repeat(" ", $spasiKolomItem);

                            $kolomHarga = $translator->trans('price', [], 'printing') . " ($symbol)";
                            $spasiKolomHarga = $lebarKolom3 - (strlen($kolomHarga) + $marginKiriKolom);
                            $barisNamaKolom .= "|" . str_repeat(" ", $marginKiriKolom) . $kolomHarga . str_repeat(" ", $spasiKolomHarga) . "|";

                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisNamaKolom . "\r\n");
                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                            $kolomNomorPembayaran = strval($num);
                            $spasiKolomNomorPembayaran = $lebarKolom1 - (strlen($kolomNomorPembayaran) + $marginKananKolom);
                            $barisPembayaran = "|"
                                . str_repeat(" ", $spasiKolomNomorPembayaran)
                                . $kolomNomorPembayaran
                                . str_repeat(" ", $marginKananKolom)
                            ;

                            $kolomItemPembayaran = $biaya->getNama();
                            $spasiKolomItemPembayaran = $lebarKolom2 - (strlen($kolomItemPembayaran) + $marginKiriKolom);
                            $barisPembayaran .= "|"
                                . str_repeat(" ", $marginKiriKolom)
                                . $kolomItemPembayaran
                                . str_repeat(" ", $spasiKolomItemPembayaran)
                            ;

                            $kolomHargaPembayaran = number_format($biaya->getNominal(), 0, ',', '.');
                            $spasiKolomHargaPembayaran = $lebarKolom3 - (strlen($kolomHargaPembayaran) + $marginKananKolom);
                            $barisPembayaran .= "|"
                                . str_repeat(" ", $spasiKolomHargaPembayaran)
                                . $kolomHargaPembayaran
                                . str_repeat(" ", $marginKananKolom)
                                . "|"
                            ;

                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisPembayaran . "\r\n");

                        } elseif ($twoPageFormat == 2 && $num == $maxitemPageone) {

                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                            $barisKeteranganBerlanjut = $translator->trans('continue.to.pagetwo', [], 'printing');
                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisKeteranganBerlanjut . "\r\n");
                            $commands->addContent("\r\n");

                            $labelwidthHal2 = 18;

                            $barisHalaman1 = "(" . $translator->trans('page', [], 'printing') . " 1/2)";
                            $spasiBarisHalaman1 = str_repeat(" ", ($maxwidth2 - strlen($barisHalaman1)));
                            $commands->addContent(str_repeat(" ", $marginBadan) . $spasiBarisHalaman1 . $barisHalaman1 . "\r\n");

                            $nomorKwitansiHal2 = $translator->trans('receiptnum', [], 'printing');
                            $spasi = str_repeat(" ", ($labelwidthHal2 - strlen($nomorKwitansiHal2)));
                            $barisNomorKwitansiHal2 = $nomorKwitansiHal2 . $spasi . ": " . $transaksi->getNomorTransaksi();

                            $nomorIdentitasHal2 = $translator->trans('identitas.sisdik', [], 'printing')
                                . ' / '
                                . $translator->trans('nomor.induk', [], 'printing')
                            ;
                            $spasi = str_repeat(" ", ($labelwidthHal2 - strlen($nomorIdentitasHal2)));
                            $barisNomorIdentitasHal2 = $nomorIdentitasHal2
                                . $spasi
                                . ": "
                                . $transaksi->getPembayaranSekali()->getSiswa()->getNomorIndukSistem()
                                . ' / '
                                . $transaksi->getPembayaranSekali()->getSiswa()->getNomorInduk()
                            ;

                            $pengisiBaris1 = strlen($barisNomorKwitansiHal2);
                            $pengisiBaris2 = strlen($barisNomorIdentitasHal2);
                            $pengisiBarisTerbesar = $pengisiBaris1 > $pengisiBaris2 ? $pengisiBaris1 : $pengisiBaris2;

                            $sisaBaris = $maxwidth2 - $pengisiBarisTerbesar;

                            $commands->addContent(str_repeat(" ", $marginBadan) . str_repeat(" ", $sisaBaris) . $barisNomorKwitansiHal2 . "\r\n");
                            $commands->addContent(str_repeat(" ", $marginBadan) . str_repeat(" ", $sisaBaris) . $barisNomorIdentitasHal2 . "\r\n");

                            $barisKeteranganLanjutan = $translator->trans('continued.from.pageone', [], 'printing');
                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisKeteranganLanjutan . "\r\n");
                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                            $kolomNomor = $translator->trans('num', [], 'printing');
                            $spasiKolomNomor = $lebarKolom1 - (strlen($kolomNomor) + $marginKiriKolom);
                            $barisNamaKolom = "|" . str_repeat(" ", $marginKiriKolom) . $kolomNomor . str_repeat(" ", $spasiKolomNomor);

                            $kolomItem = $translator->trans('paymentitem', [], 'printing');
                            $spasiKolomItem = $lebarKolom2 - (strlen($kolomItem) + $marginKiriKolom);
                            $barisNamaKolom .= "|" . str_repeat(" ", $marginKiriKolom) . $kolomItem . str_repeat(" ", $spasiKolomItem);

                            $kolomHarga = $translator->trans('price', [], 'printing') . " ($symbol)";
                            $spasiKolomHarga = $lebarKolom3 - (strlen($kolomHarga) + $marginKiriKolom);
                            $barisNamaKolom .= "|"
                                . str_repeat(" ", $marginKiriKolom)
                                . $kolomHarga
                                . str_repeat(" ", $spasiKolomHarga)
                                . "|"
                            ;

                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisNamaKolom . "\r\n");
                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                            $kolomNomorPembayaran = strval($num);
                            $spasiKolomNomorPembayaran = $lebarKolom1 - (strlen($kolomNomorPembayaran) + $marginKananKolom);
                            $barisPembayaran = "|"
                                . str_repeat(" ", $spasiKolomNomorPembayaran)
                                . $kolomNomorPembayaran
                                . str_repeat(" ", $marginKananKolom)
                            ;

                            $kolomItemPembayaran = $biaya->getNama();
                            $spasiKolomItemPembayaran = $lebarKolom2 - (strlen($kolomItemPembayaran) + $marginKiriKolom);
                            $barisPembayaran .= "|"
                                . str_repeat(" ", $marginKiriKolom)
                                . $kolomItemPembayaran
                                . str_repeat(" ", $spasiKolomItemPembayaran)
                            ;

                            $kolomHargaPembayaran = number_format($biaya->getNominal(), 0, ',', '.');
                            $spasiKolomHargaPembayaran = $lebarKolom3 - (strlen($kolomHargaPembayaran) + $marginKananKolom);
                            $barisPembayaran .= "|"
                                . str_repeat(" ", $spasiKolomHargaPembayaran)
                                . $kolomHargaPembayaran
                                . str_repeat(" ", $marginKananKolom)
                                . "|"
                            ;

                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisPembayaran . "\r\n");

                        } else {
                            $kolomNomorPembayaran = strval($num);
                            $spasiKolomNomorPembayaran = $lebarKolom1 - (strlen($kolomNomorPembayaran) + $marginKananKolom);
                            $barisPembayaran = "|"
                                . str_repeat(" ", $spasiKolomNomorPembayaran)
                                . $kolomNomorPembayaran
                                . str_repeat(" ", $marginKananKolom)
                            ;

                            $kolomItemPembayaran = $biaya->getNama();
                            $spasiKolomItemPembayaran = $lebarKolom2 - (strlen($kolomItemPembayaran) + $marginKiriKolom);
                            $barisPembayaran .= "|"
                                . str_repeat(" ", $marginKiriKolom)
                                . $kolomItemPembayaran
                                . str_repeat(" ", $spasiKolomItemPembayaran)
                            ;

                            $kolomHargaPembayaran = number_format($biaya->getNominal(), 0, ',', '.');
                            $spasiKolomHargaPembayaran = $lebarKolom3 - (strlen($kolomHargaPembayaran) + $marginKananKolom);
                            $barisPembayaran .= "|"
                                . str_repeat(" ", $spasiKolomHargaPembayaran)
                                . $kolomHargaPembayaran
                                . str_repeat(" ", $marginKananKolom)
                                . "|"
                            ;

                            $commands->addContent(str_repeat(" ", $marginBadan) . $barisPembayaran . "\r\n");
                        }
                    }

                    $num++;
                }

                $commands->addContent(str_repeat(" ", $marginBadan) . $barisGarisTabel . "\r\n");

                $lebarKolom4 = 55;
                $lebarKolom5 = 22;
                $lebarKolom6 = 23;

                $kolomKeterangan = $translator->trans('description', [], 'printing');
                $kolomKeterangan .= " : " . $transaksi->getKeterangan();
                $kolomKeterangan = strlen($kolomKeterangan) > $lebarKolom4 ?
                    substr($kolomKeterangan, 0, ($lebarKolom4 - 3)) . "..." : $kolomKeterangan
                ;
                $spasiKolomKeterangan = $lebarKolom4 - (strlen($kolomKeterangan));
                $barisKolomTotal = $kolomKeterangan . str_repeat(" ", $spasiKolomKeterangan);

                if ($pembayaran->getAdaPotongan()) {

                    $kolomSubtotal = $translator->trans('subtotal', [], 'printing');
                    $spasiKolomSubtotal = $lebarKolom5 - (strlen($kolomSubtotal) + $marginKananKolom);
                    $barisKolomSubtotal = $barisKolomTotal
                        . str_repeat(" ", $spasiKolomSubtotal)
                        . $kolomSubtotal
                        . str_repeat(" ", $marginKananKolom)
                        . ":"
                    ;

                    $kolomSubtotalHarga = number_format($totalNominalTransaksi, 0, ',', '.');
                    $spasiKolomSubtotalHarga = $lebarKolom6 - (strlen($kolomSubtotalHarga) + $marginKananKolom);
                    $barisKolomSubtotal .= str_repeat(" ", $spasiKolomSubtotalHarga)
                        . $kolomSubtotalHarga
                        . str_repeat(" ", $marginKananKolom)
                        . " "
                    ;

                    $commands->addContent(str_repeat(" ", $marginBadan) . $barisKolomSubtotal . "\r\n");

                    $nominalPotongan = 0;
                    $persenPotongan = "";
                    if ($pembayaran->getJenisPotongan() == 'nominal') {
                        $nominalPotongan = $pembayaran->getNominalPotongan();
                    } else {
                        $nominalPotongan = $pembayaran->getPersenPotonganDinominalkan();
                        $persenPotongan = " (" . $pembayaran->getPersenPotongan() . "%)";
                    }
                    $kolomPotongan = $translator->trans('discount', [], 'printing');
                    $spasiKolomPotongan = $lebarKolom4 + $lebarKolom5 - (strlen($kolomPotongan) + $marginKananKolom);
                    $barisKolomPotongan = str_repeat(" ", $spasiKolomPotongan) . $kolomPotongan . str_repeat(" ", $marginKananKolom) . ":";

                    $kolomPotonganHarga = number_format($nominalPotongan, 0, ',', '.');
                    $spasiKolomPotonganHarga = $lebarKolom6 - (strlen($kolomPotonganHarga) + $marginKananKolom);
                    $barisKolomPotongan .= str_repeat(" ", $spasiKolomPotonganHarga)
                        . $kolomPotonganHarga
                        . str_repeat(" ", $marginKananKolom)
                        . $persenPotongan
                    ;

                    $commands->addContent(str_repeat(" ", $marginBadan) . $barisKolomPotongan . "\r\n");

                    $kolomTotal = $translator->trans('total', [], 'printing');
                    $spasiKolomTotal = $lebarKolom4 + $lebarKolom5 - (strlen($kolomTotal) + $marginKananKolom);
                    $barisKolomTotal = str_repeat(" ", $spasiKolomTotal) . $kolomTotal . str_repeat(" ", $marginKananKolom) . ":";

                    $kolomTotalHarga = number_format($totalNominalTransaksi - $nominalPotongan, 0, ',', '.');
                    $spasiKolomTotalHarga = $lebarKolom6 - (strlen($kolomTotalHarga) + $marginKananKolom);
                    $barisKolomTotal .= str_repeat(" ", $spasiKolomTotalHarga) . $kolomTotalHarga . str_repeat(" ", $marginKananKolom) . " ";

                    $commands->addContent(str_repeat(" ", $marginBadan) . $barisKolomTotal . "\r\n");
                    $commands->addContent("\r\n");

                    if (count($daftarBiayaSekali) < $maxitemPageone) {
                        $maxJarakVertikal = $maxitemPageone;
                        $jarakVertikal = str_repeat("\r\n", $maxJarakVertikal - count($daftarBiayaSekali));
                        $commands->addContent($jarakVertikal);
                    }
                    if ($twoPageFormat == 1) {
                        $maxJarakVertikal = 10;
                        $jarakVertikal = str_repeat("\r\n", $maxJarakVertikal - (count($daftarBiayaSekali) - ($maxitemPageone + 1)));
                        $commands->addContent($jarakVertikal);
                    }
                    if ($twoPageFormat == 2) {
                        $maxJarakVertikal = 10;
                        $jarakVertikal = str_repeat("\r\n", $maxJarakVertikal - (count($daftarBiayaSekali) - 15));
                        $commands->addContent($jarakVertikal);
                    }

                } else {

                    $kolomTotal = $translator->trans('total', [], 'printing');
                    $spasiKolomTotal = $lebarKolom5 - (strlen($kolomTotal) + $marginKananKolom);
                    $barisKolomTotal .= str_repeat(" ", $spasiKolomTotal) . $kolomTotal . str_repeat(" ", $marginKananKolom) . ":";

                    $kolomTotalHarga = number_format($totalNominalTransaksi, 0, ',', '.');
                    $spasiKolomTotalHarga = $lebarKolom6 - (strlen($kolomTotalHarga) + $marginKananKolom);
                    $barisKolomTotal .= str_repeat(" ", $spasiKolomTotalHarga) . $kolomTotalHarga . str_repeat(" ", $marginKananKolom) . " ";

                    $commands->addContent(str_repeat(" ", $marginBadan) . $barisKolomTotal . "\r\n");
                    $commands->addContent("\r\n");

                    if (count($daftarBiayaSekali) < $maxitemPageone) {
                        $maxJarakVertikal = 9;
                        $jarakVertikal = str_repeat("\r\n", $maxJarakVertikal - count($daftarBiayaSekali));
                        $commands->addContent($jarakVertikal);
                    }
                    if ($twoPageFormat == 1) {
                        $maxJarakVertikal = 12;
                        $jarakVertikal = str_repeat("\r\n", $maxJarakVertikal - (count($daftarBiayaSekali) - ($maxitemPageone + 1)));
                        $commands->addContent($jarakVertikal);
                    }
                    if ($twoPageFormat == 2) {
                        $maxJarakVertikal = 12;
                        $jarakVertikal = str_repeat("\r\n", $maxJarakVertikal - (count($daftarBiayaSekali) - 15));
                        $commands->addContent($jarakVertikal);
                    }

                }

                /****** selesai kwitansi format non-cicilan ******/
            }

            $marginKiriTtd = 20;
            $lebarKolom7 = 62;
            $lebarKolom8 = 59;

            $kolomNama1 = $translator->trans('nama.siswa', [], 'printing');
            $spasiKolomNama1 = $lebarKolom7 - (strlen($kolomNama1) + $marginKiriTtd);
            $barisTandatangan1 = str_repeat(" ", $marginKiriTtd) . $kolomNama1 . str_repeat(" ", $spasiKolomNama1);

            $kolomPenerima1 = $translator->trans('cashier.or.treasurer', [], 'printing');
            $spasiKolomPenerima1 = $lebarKolom8 - strlen($kolomPenerima1);
            $barisTandatangan1 .= $kolomPenerima1 . str_repeat(" ", $spasiKolomPenerima1);

            $commands->addContent($barisTandatangan1 . "\r\n");
            $commands->addContent("\r\n");
            $commands->addContent("\r\n");
            $commands->addContent("\r\n");

            $kolomNama2 = $siswa->getNamaLengkap();
            $spasiKolomNama2 = $lebarKolom7 - (strlen($kolomNama2) + $marginKiriTtd);
            $barisTandatangan2 = str_repeat(" ", $marginKiriTtd) . $kolomNama2 . str_repeat(" ", $spasiKolomNama2);

            $kolomPenerima2 = $transaksi->getDibuatOleh()->getName();
            $spasiKolomPenerima2 = $lebarKolom8 - strlen($kolomPenerima2);
            $barisTandatangan2 .= $kolomPenerima2 . str_repeat(" ", $spasiKolomPenerima2);

            if ($twoPages === true) {
                $commands->addContent($barisTandatangan2 . "(" . $translator->trans('page', [], 'printing') . " 2/2)");
            } else {
                $commands->addContent($barisTandatangan2 . "(" . $translator->trans('page', [], 'printing') . " 1/1)");
            }

            $commands->addFormFeed();
            $commands->addResetCommand();

            $fp = fopen($documenttarget, "w");

            if (!$fp) {
                throw new IOException($translator->trans("exception.directprint.file"));
            } else {
                fwrite($fp, $commands->getCommands());
                fclose($fp);
            }

            $response = new Response(file_get_contents($documenttarget), 200);
            $d = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filetarget);
            $response->headers->set('Content-Disposition', $d);
            $response->headers->set('Content-Description', 'Dokumen kwitansi pembayaran biaya sekali bayar');
            $response->headers->set('Content-Type', 'application/vnd.sisdik.directprint');
            $response->headers->set('Content-Transfer-Encoding', 'binary');
            $response->headers->set('Expires', '0');
            $response->headers->set('Cache-Control', 'must-revalidate');
            $response->headers->set('Pragma', 'public');
            $response->headers->set('Content-Length', filesize($documenttarget));

        } else {

            $filetarget = $transaksi->getNomorTransaksi() . ".sisdik.pdf";
            $documenttarget = $schoolReceiptDir . DIRECTORY_SEPARATOR . $tahun . DIRECTORY_SEPARATOR . $bulan . DIRECTORY_SEPARATOR . $filetarget;

            $totalHarga = 0;
            foreach ($daftarBiayaSekali as $biaya) {
                if ($biaya instanceof DaftarBiayaSekali) {
                    $namaItemPembayaranCicilan = $biaya->getNama();
                    $totalHarga += $biaya->getNominal();
                }
            }

            /* @var $facade Facade */
            $facade = $this->get('ps_pdf.facade');
            $tmpResponse = new Response();

            $this
                ->render('LanggasSisdikBundle:PembayaranBiayaSekali:receipts.pdf.twig', [
                    'sekolah' => $sekolah,
                    'siswa' => $siswa,
                    'pembayaran' => $pembayaran,
                    'transaksi' => $transaksi,
                    'totalHarga' => $totalHarga,
                    'adaCicilan' => $adaCicilan,
                    'namaItemPembayaranCicilan' => $namaItemPembayaranCicilan,
                    'nomorCicilan' => $nomorCicilan,
                    'totalPembayaranHinggaTransaksiTerpilih' => $totalPembayaranHinggaTransaksiTerpilih,
                ], $tmpResponse)
            ;
            $xml = $tmpResponse->getContent();
            $content = $facade->render($xml);

            $fp = fopen($documenttarget, "w");

            if (!$fp) {
                throw new IOException($translator->trans("exception.open.file.pdf"));
            } else {
                fwrite($fp, $content);
                fclose($fp);
            }

            $response = new Response(file_get_contents($documenttarget), 200);
            $d = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filetarget);
            $response->headers->set('Content-Disposition', $d);
            $response->headers->set('Content-Description', 'Dokumen kwitansi biaya sekali bayar');
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Transfer-Encoding', 'binary');
            $response->headers->set('Expires', '0');
            $response->headers->set('Cache-Control', 'must-revalidate');
            $response->headers->set('Pragma', 'public');
            $response->headers->set('Content-Length', filesize($documenttarget));
        }

        return $response;
    }

    /**
     * @return Sekolah
     */
    private function getSekolah()
    {
        return $this->getUser()->getSekolah();
    }
}
