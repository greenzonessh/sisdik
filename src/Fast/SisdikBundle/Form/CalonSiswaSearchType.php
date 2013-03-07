<?php

namespace Fast\SisdikBundle\Form;
use Fast\SisdikBundle\Entity\PanitiaPendaftaran;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Fast\SisdikBundle\Entity\Sekolah;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class CalonSiswaSearchType extends AbstractType
{
    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $user = $this->container->get('security.context')->getToken()->getUser();
        $sekolah = $user->getSekolah();

        $em = $this->container->get('doctrine')->getManager();
        if (is_object($sekolah) && $sekolah instanceof Sekolah) {

            $qb = $em->createQueryBuilder()->select('t')->from('FastSisdikBundle:PanitiaPendaftaran', 't')
                    ->leftJoin('t.tahunmasuk', 't2')->where('t2.sekolah = :sekolah')
                    ->setParameter('sekolah', $sekolah->getId());
            $results = $qb->getQuery()->getResult();
            $daftarTahunmasuk = '';
            foreach ($results as $entity) {
                if (is_object($entity) && $entity instanceof PanitiaPendaftaran) {
                    if (is_array($entity->getPanitia()) && in_array($user->getId(), $entity->getPanitia())) {
                        $daftarTahunmasuk .= $entity->getTahunmasuk()->getId() . ',';
                    }
                }
            }
            $daftarTahunmasuk = preg_replace('/,$/', '', $daftarTahunmasuk);

            if ($daftarTahunmasuk == '') {
                throw new AccessDeniedException(
                        $this->container->get('translator')->trans('exception.register.as.committee'));
            }

            $querybuilder1 = $em->createQueryBuilder()->select('t')->from('FastSisdikBundle:Tahunmasuk', 't')
                    ->where('t.sekolah = :sekolah')->andWhere('t.id IN (:daftartahunmasuk)')
                    ->orderBy('t.tahun', 'DESC')->setParameter('sekolah', $sekolah->getId())
                    ->setParameter('daftartahunmasuk', $daftarTahunmasuk);

            $builder
                    ->add('tahunmasuk', 'entity',
                            array(
                                    'class' => 'FastSisdikBundle:Tahunmasuk',
                                    'label' => 'label.yearentry.entry', 'multiple' => false,
                                    'expanded' => false, 'property' => 'tahun',
                                    'empty_value' => 'label.selectyear', 'required' => false,
                                    'query_builder' => $querybuilder1,
                                    'attr' => array(
                                        'class' => 'medium'
                                    ), 'label_render' => false,
                            ));
        }
        $builder
                ->add('searchkey', null,
                        array(
                                'required' => false,
                                'attr' => array(
                                    'class' => 'small search-query'
                                ), 'label_render' => false,
                        ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver) {
        $resolver
                ->setDefaults(
                        array(
                            'csrf_protection' => false,
                        ));
    }

    public function getName() {
        // return '';
        // return 'fast_sisdikbundle_siswasearchtype';
        return 'searchform';
    }
}
