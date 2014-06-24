<?php
namespace Fast\SisdikBundle\Form;

use Fast\SisdikBundle\Entity\Sekolah;
use Fast\SisdikBundle\Form\EventListener\JumlahBayarSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use JMS\DiExtraBundle\Annotation\FormType;

/**
 * @FormType
 */
class SiswaApplicantReportSearchType extends AbstractType
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();
        $sekolah = $user->getSekolah();

        $em = $this->container->get('doctrine')->getManager();

        $querybuilder1 = $em->createQueryBuilder()
            ->select('t')
            ->from('FastSisdikBundle:Gelombang', 't')
            ->where('t.sekolah = :sekolah')
            ->orderBy('t.urutan', 'ASC')
            ->setParameter('sekolah', $sekolah->getId())
        ;
        $builder
            ->add('gelombang', 'entity', [
                'class' => 'FastSisdikBundle:Gelombang',
                'multiple' => false,
                'expanded' => false,
                'property' => 'nama',
                'empty_value' => 'label.selectadmissiongroup',
                'query_builder' => $querybuilder1,
                'attr' => [
                    'class' => 'medium',
                ], 'required' => false,
                'label_render' => false,
                'horizontal' => false,
            ])
            ->add('dariTanggal', 'date', [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'attr' => [
                    'class' => 'date small',
                    'placeholder' => 'label.dari.tanggal',
                ],
                'required' => false,
                'label_render' => false,
                'horizontal' => false,
            ])
            ->add('hinggaTanggal', 'date', [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'attr' => [
                    'class' => 'date small',
                    'placeholder' => 'label.hingga.tanggal.singkat',
                ],
                'required' => false,
                'label_render' => false,
                'horizontal' => false,
            ])
            ->add('searchkey', null, [
                'attr' => [
                    'class' => 'medium search-query',
                    'placeholder' => 'label.searchkey',
                ],
                'required' => false,
                'label_render' => false,
                'horizontal' => false,
            ])
            ->add('jenisKelamin', 'choice', [
                'required' => false,
                'choices' => [
                    'L' => 'male',
                    'P' => 'female'
                ],
                'attr' => [
                    'class' => 'medium'
                ],
                'label_render' => false,
                'empty_value' => 'label.gender.empty.select',
                'horizontal' => false,
            ])
            ->add('sekolahAsal', new EntityHiddenType($em), [
                'class' => 'FastSisdikBundle:SekolahAsal',
                'attr' => [
                    'class' => 'id-sekolah-asal',
                ],
                'required' => false,
                'label_render' => false,
                'horizontal' => false,
            ])
            ->add('namaSekolahAsal', 'text', [
                'attr' => [
                    'class' => 'xlarge nama-sekolah-asal ketik-pilih-tambah',
                    'placeholder' => 'label.sekolah.asal',
                ],
                'required' => false,
                'label_render' => false,
                'horizontal' => false,
            ])
            ->add('referensi', new EntityHiddenType($em), [
                'class' => 'FastSisdikBundle:Referensi',
                'attr' => [
                    'class' => 'large id-referensi',
                ],
                'required' => false,
                'label_render' => false,
                'horizontal' => false,
            ])
            ->add('namaReferensi', 'text', [
                'attr' => [
                    'class' => 'xlarge nama-referensi ketik-pilih-tambah',
                    'placeholder' => 'label.perujuk',
                ],
                'required' => false,
                'label_render' => false,
                'horizontal' => false,
            ])
        ;

        $builder->addEventSubscriber(new JumlahBayarSubscriber($this->container->get('translator')));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'csrf_protection' => false,
            ])
        ;
    }

    public function getName()
    {
        return 'searchform';
    }
}
