<?php
namespace Langgas\SisdikBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use JMS\DiExtraBundle\Annotation\FormType;

/**
 * @FormType
 */
class SekolahFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nama', null, [
                'required' => true,
                'label' => 'label.schoolname',
                'attr' => [
                    'class' => 'xlarge',
                ],
            ])
            ->add('kode', null, [
                'required' => true,
                'label' => 'label.code',
                'attr' => array(
                    'class' => 'mini',
                ),
            ])
            ->add('alamat', 'textarea', [
                'label' => 'label.address',
                'attr' => [
                    'class' => 'xlarge',
                ],
            ])
            ->add('kodepos', null, [
                'label' => 'label.postalcode',
                'attr' => [
                    'class' => 'small',
                ],
            ])
            ->add('telepon', null, [
                'label' => 'label.phone',
                'attr' => [
                    'class' => 'medium',
                ],
            ])
            ->add('fax', null, [
                'label' => 'label.fax',
                'attr' => [
                    'class' => 'medium',
                ],
            ])
            ->add('email', 'email', [
                'required' => true,
                'label' => 'label.email',
                'attr' => [
                    'class' => 'xlarge',
                ],
            ])
            ->add('norekening', null, [
                'label' => 'label.account',
                'attr' => [
                    'class' => 'large',
                ],
            ])
            ->add('bank', null, [
                'label' => 'label.bank',
                'attr' => [
                    'class' => 'large',
                ],
            ])
            ->add('kepsek', null, [
                'required' => true,
                'label' => 'label.headmaster',
                'attr' => [
                    'class' => 'large',
                ],
            ])
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => 'Langgas\SisdikBundle\Entity\Sekolah',
            ])
        ;
    }

    public function getName()
    {
        return 'langgas_sisdikbundle_sekolahformtype';
    }
}