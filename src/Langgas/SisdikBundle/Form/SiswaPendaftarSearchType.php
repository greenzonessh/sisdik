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

/**
 * @FormType
 */
class SiswaPendaftarSearchType extends AbstractType
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
            ->add('gelombang', 'entity', [
                'class' => 'LanggasSisdikBundle:Gelombang',
                'multiple' => false,
                'expanded' => false,
                'property' => 'nama',
                'placeholder' => 'label.selectadmissiongroup',
                'required' => false,
                'query_builder' => function (EntityRepository $repository) use ($sekolah) {
                    $qb = $repository->createQueryBuilder('gelombang')
                        ->where('gelombang.sekolah = :sekolah')
                        ->orderBy('gelombang.urutan', 'ASC')
                        ->setParameter('sekolah', $sekolah)
                    ;

                    return $qb;
                },
                'attr' => [
                    'class' => 'medium',
                ],
                'label_render' => false,
                'horizontal' => false,
            ])
            ->add('searchkey', null, [
            'required' => false,
            'attr' => [
                'class' => 'medium search-query',
                'placeholder' => 'label.searchkey',
            ],
            'label_render' => false,
            'horizontal' => false,
        ]);
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
        return 'sisdik_caripendaftar';
    }
}
