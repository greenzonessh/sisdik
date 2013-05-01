<?php

namespace Fast\SisdikBundle\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * DokumenSiswa
 *
 * @ORM\Table(name="dokumen_siswa")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class DokumenSiswa
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var boolean
     *
     * @ORM\Column(name="lengkap", type="boolean", nullable=false)
     */
    private $lengkap;

    /**
     * @var string
     *
     * @ORM\Column(name="nama_file", type="string", length=255, nullable=true)
     */
    private $namaFile;

    /**
     * @var string
     *
     * @ORM\Column(name="nama_file_disk", type="string", length=255, nullable=true)
     */
    private $namaFileDisk;

    /**
     * @var \JenisDokumenSiswa
     *
     * @ORM\ManyToOne(targetEntity="JenisDokumenSiswa")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="jenis_dokumen_siswa_id", referencedColumnName="id", nullable=false)
     * })
     */
    private $jenisDokumenSiswa;

    /**
     * @var \Siswa
     *
     * @ORM\ManyToOne(targetEntity="Siswa")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="siswa_id", referencedColumnName="id", nullable=false)
     * })
     */
    private $siswa;

    /**
     * @var ArrayCollection dokumenSiswa
     */
    private $dokumenSiswa;

    public function __construct() {
        $this->dokumenSiswa = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set lengkap
     *
     * @param boolean $lengkap
     * @return DokumenSiswa
     */
    public function setLengkap($lengkap) {
        $this->lengkap = $lengkap;

        return $this;
    }

    /**
     * Get lengkap
     *
     * @return boolean
     */
    public function isLengkap() {
        return $this->lengkap;
    }

    /**
     * Set namaFile
     *
     * @param string $namaFile
     * @return DokumenSiswa
     */
    public function setNamaFile($namaFile) {
        $this->namaFile = $namaFile;

        return $this;
    }

    /**
     * Get namaFile
     *
     * @return string
     */
    public function getNamaFile() {
        return $this->namaFile;
    }

    /**
     * Set namaFileDisk
     *
     * @param string $namaFileDisk
     * @return DokumenSiswa
     */
    public function setNamaFileDisk($namaFileDisk) {
        $this->namaFileDisk = $namaFileDisk;

        return $this;
    }

    /**
     * Get namaFileDisk
     *
     * @return string
     */
    public function getNamaFileDisk() {
        return $this->namaFileDisk;
    }

    /**
     * Set jenisDokumenSiswa
     *
     * @param \Fast\SisdikBundle\Entity\JenisDokumenSiswa $jenisDokumenSiswa
     * @return DokumenSiswa
     */
    public function setJenisDokumenSiswa(
            \Fast\SisdikBundle\Entity\JenisDokumenSiswa $jenisDokumenSiswa = null) {
        $this->jenisDokumenSiswa = $jenisDokumenSiswa;

        return $this;
    }

    /**
     * Get jenisDokumenSiswa
     *
     * @return \Fast\SisdikBundle\Entity\JenisDokumenSiswa
     */
    public function getJenisDokumenSiswa() {
        return $this->jenisDokumenSiswa;
    }

    /**
     * Set siswa
     *
     * @param \Fast\SisdikBundle\Entity\Siswa $siswa
     * @return DokumenSiswa
     */
    public function setSiswa(\Fast\SisdikBundle\Entity\Siswa $siswa = null) {
        $this->siswa = $siswa;

        return $this;
    }

    /**
     * Get siswa
     *
     * @return \Fast\SisdikBundle\Entity\Siswa
     */
    public function getSiswa() {
        return $this->siswa;
    }

    /**
     * Get dokumenSiswa
     *
     * @return \Doctrine\Common\Collections\ArrayCollection $dokumenSiswa
     */
    public function getDokumenSiswa() {
        return $this->dokumenSiswa;
    }

    /**
     * Set dokumenSiswa
     *
     * @param ArrayCollection $dokumenSiswa
     */
    public function setDokumenSiswa(ArrayCollection $dokumenSiswa) {
        $this->dokumenSiswa = $dokumenSiswa;
    }

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function preSave() {
        foreach ($this->dokumenSiswa as $dokumen) {
            if ($dokumen instanceof Dokumen) {
                $this->lengkap = $dokumen->isLengkap();
                $this->namaFile = $dokumen->getNamaFile();
                $this->namaFileDisk = $dokumen->getNamaFileDisk();
                $this->jenisDokumenSiswa = $dokumen->getJenisDokumenSiswa();
                $this->siswa = $dokumen->getSiswa();
            }
        }
    }
}
