<?php

namespace Langgas\SisdikBundle\Form;

use Doctrine\ORM\EntityRepository;
use Langgas\SisdikBundle\Entity\Sekolah;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use JMS\DiExtraBundle\Annotation\FormType;
use JMS\DiExtraBundle\Annotation\Inject;
use JMS\DiExtraBundle\Annotation\InjectParams;
use Langgas\SisdikBundle\Entity\BiayaRutin;
use Langgas\SisdikBundle\Entity\LayananSmsPeriodik;
use Langgas\SisdikBundle\Entity\JadwalKehadiran;

/**
 * @FormType
 */
class BiayaRutinType extends AbstractType
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @InjectParams({
     *     "tokenStorage" = @Inject("security.token_storage")
     * })
     *
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return Sekolah
     */
    private function getSekolah()
    {
        return $this->tokenStorage->getToken()->getUser()->getSekolah();
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $sekolah = $this->getSekolah();

        $builder
            ->add('tahun', 'entity', [
                'class' => 'LanggasSisdikBundle:Tahun',
                'label' => 'label.year.entry',
                'multiple' => false,
                'expanded' => false,
                'property' => 'tahun',
                'placeholder' => false,
                'required' => true,
                'query_builder' => function (EntityRepository $repository) use ($sekolah) {
                    $qb = $repository->createQueryBuilder('tahun')
                        ->where('tahun.sekolah = :sekolah')
                        ->orderBy('tahun.tahun', 'DESC')
                        ->setParameter('sekolah', $sekolah)
                    ;

                    return $qb;
                },
                'attr' => [
                    'class' => 'small',
                ],
                'read_only' => $options['mode'] == 'edit' ? true : false,
                'disabled' => $options['mode'] == 'edit' ? true : false,
                'horizontal_input_wrapper_class' => 'col-sm-4 col-md-3 col-lg-2',
            ])
            ->add('penjurusan', 'entity', [
                'class' => 'LanggasSisdikBundle:Penjurusan',
                'label' => 'label.placement.study',
                'multiple' => false,
                'expanded' => false,
                'property' => 'optionLabel',
                'placeholder' => 'label.semua.penjurusan.studi',
                'required' => false,
                'query_builder' => function (EntityRepository $repository) use ($sekolah) {
                    $qb = $repository->createQueryBuilder('penjurusan')
                        ->where('penjurusan.sekolah = :sekolah')
                        ->orderBy('penjurusan.root', 'ASC')
                        ->addOrderBy('penjurusan.lft', 'ASC')
                        ->setParameter('sekolah', $sekolah)
                    ;

                    return $qb;
                },
                'attr' => [
                    'class' => 'xlarge',
                ],
                'read_only' => $options['mode'] == 'edit' ? true : false,
                'disabled' => $options['mode'] == 'edit' ? true : false,
            ])
            ->add('jenisbiaya', 'entity', [
                'class' => 'LanggasSisdikBundle:Jenisbiaya',
                'label' => 'label.fee.type.entry',
                'multiple' => false,
                'expanded' => false,
                'property' => 'nama',
                'placeholder' => false,
                'required' => true,
                'query_builder' => function (EntityRepository $repository) use ($sekolah) {
                    $qb = $repository->createQueryBuilder('jenisbiaya')
                        ->where('jenisbiaya.sekolah = :sekolah')
                        ->orderBy('jenisbiaya.nama', 'ASC')
                        ->setParameter('sekolah', $sekolah)
                    ;

                    return $qb;
                },
                'attr' => [
                    'class' => 'xlarge',
                ],
                'read_only' => $options['mode'] == 'edit' ? true : false,
                'disabled' => $options['mode'] == 'edit' ? true : false,
            ])
            ->add('nominal', 'money', [
                'currency' => 'IDR',
                'required' => true,
                'precision' => 0,
                'grouping' => 3,
                'attr' => [
                    'class' => 'large',
                ],
                'horizontal_input_wrapper_class' => 'col-sm-6 col-md-5 col-lg-4',
            ])
            ->add('perulangan', 'choice', [
                'choices' => BiayaRutin::getDaftarPerulangan(),
                'preferred_choices' => ['c-bulanan'],
                'required' => true,
                'multiple' => false,
                'expanded' => false,
                'attr' => [
                    'class' => 'medium',
                ],
                'read_only' => $options['mode'] == 'edit' ? true : false,
                'disabled' => $options['mode'] == 'edit' ? true : false,
            ])
            ->add('mingguanHariKe', 'choice', [
                'label' => 'label.day',
                'choices' => JadwalKehadiran::getNamaHari(),
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'placeholder' => 'label.selectweekday',
                'attr' => [
                    'class' => 'medium',
                ],
                'help_block' => 'help.untuk.perulangan.mingguan',
                'read_only' => $options['mode'] == 'edit' ? true : false,
                'disabled' => $options['mode'] == 'edit' ? true : false,
            ])
            ->add('bulananHariKe', 'choice', [
                'label' => 'label.monthday',
                'choices' => JadwalKehadiran::getAngkaHariSebulan(),
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'placeholder' => 'label.selectmonthday',
                'attr' => [
                    'class' => 'medium',
                ],
                'help_block' => 'help.untuk.perulangan.bulanan.atau.lebih.lama',
                'read_only' => $options['mode'] == 'edit' ? true : false,
                'disabled' => $options['mode'] == 'edit' ? true : false,
            ])
            ->add('bulanAwal', 'choice', [
                'label' => 'label.bulan.awal',
                'choices' => LayananSmsPeriodik::getDaftarNamaBulan(),
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'placeholder' => 'label.pilih.bulan',
                'attr' => [
                    'class' => 'medium',
                ],
                'help_block' => 'help.untuk.perulangan.triwulan.atau.lebih.lama',
                'read_only' => $options['mode'] == 'edit' ? true : false,
                'disabled' => $options['mode'] == 'edit' ? true : false,
            ])
            ->add('urutan', 'choice', [
                'choices' => $this->buildOrderChoices(),
                'required' => true,
                'multiple' => false,
                'expanded' => false,
                'attr' => [
                    'class' => 'small',
                ],
                'horizontal_input_wrapper_class' => 'col-sm-3 col-md-2 col-lg-1',
            ])
        ;

        if ($options['mode'] == 'edit') {
            $builder
                ->add('nominalSebelumnya', 'hidden', [
                    'required' => false,
                    'data' => $options['nominal'],
                ])
            ;
        }

        if ($options['nominal'] !== null) {
            $builder
                ->add('captcha', 'captcha', [
                    'attr' => [
                        'class' => 'medium',
                        'placeholder' => 'help.type.captcha',
                        'autocomplete' => 'off',
                    ],
                    'as_url' => true,
                    'reload' => true,
                    'help_block' => 'help.captcha.penjelasan.ubah.biaya',
                    'horizontal_input_wrapper_class' => 'col-sm-6 col-md-5 col-lg-4',
                ])
            ;
        }
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => 'Langgas\SisdikBundle\Entity\BiayaRutin',
                'mode' => 'new',
                'nominal' => null,
            ])
        ;
    }

    /**
     * @return array
     */
    public function buildOrderChoices()
    {
        return array_combine(range(1, 100), range(1, 100));
    }

    public function getName()
    {
        return 'sisdik_biayarutin';
    }
}
