<?php
namespace Langgas\SisdikBundle\Form;

use Langgas\SisdikBundle\Entity\Sekolah;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * UserFormType will be used both for ADMIN and SUPER ADMIN role,
 * so we need to put certain condition depend on the logged in user session
 */
class UserFormType extends AbstractType
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var integer
     */
    private $formoption;

    /**
     * @param ContainerInterface $container
     * @param integer            $formoption
     */
    public function __construct(ContainerInterface $container, $formoption = 0)
    {
        $this->container = $container;
        $this->formoption = $formoption;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', null, [
                'required' => true,
                'read_only' => true,
                'attr' => [
                    'class' => 'disabled large',
                ],
            ])
            ->add('email', 'email', [
                'required' => true,
                'read_only' => true,
                'attr' => [
                    'class' => 'disabled xlarge',
                ],
            ])
            ->add('name', null, [
                'required' => true,
                'label' => 'label.name.full',
                'read_only' => true,
                'attr' => [
                    'class' => 'disabled xlarge',
                ],
            ])
        ;

        if ($this->formoption == 1) {
            // role user and super admin, not registered to any school
            foreach ($this->container->getParameter('security.role_hierarchy.roles') as $keys => $values) {
                if (!($keys == 'ROLE_USER' || $keys == 'ROLE_SUPER_ADMIN')) {
                    continue;
                }
                $string = str_replace('ROLE_', ' ', $keys);
                $roles[$keys] = str_replace('_', ' ', $string);
            }

            $builder
                ->add('roles', 'choice', [
                    'choices' => $roles,
                    'label' => 'label.roles',
                    'multiple' => true,
                    'expanded' => true,
                ])
            ;
        } elseif ($this->formoption == 2) {
            // role siswa, nothing
        } elseif ($this->formoption == 3) {
            foreach ($this->container->getParameter('security.role_hierarchy.roles') as $keys => $values) {
                if ($keys == 'ROLE_USER'
                        || $keys == 'ROLE_SISWA'
                        || $keys == 'ROLE_SUPER_ADMIN'
                        || $keys == 'ROLE_PANITIA_PSB'
                        || $keys == 'ROLE_KETUA_PANITIA_PSB') {
                    continue;
                }
                $string = str_replace('ROLE_', ' ', $keys);
                $roles[$keys] = str_replace('_', ' ', $string);
            }

            $builder
                ->add('roles', 'choice', [
                    'choices' => $roles,
                    'label' => 'label.roles',
                    'multiple' => true,
                    'expanded' => true,
                ])
            ;
        }

        if ($this->formoption > 1) {
            if ($this->container->get('security.context')->isGranted('ROLE_SUPER_ADMIN')) {
                $builder
                    ->add('sekolah', 'entity', [
                        'class' => 'LanggasSisdikBundle:Sekolah',
                        'label' => 'label.school',
                        'multiple' => false,
                        'expanded' => false,
                        'property' => 'nama',
                        'required' => true,
                    ])
                ;
            } else {
                $user = $this->container->get('security.context')->getToken()->getUser();
                $sekolah = $user->getSekolah();

                if (is_object($sekolah) && $sekolah instanceof Sekolah) {
                    $em = $this->container->get('doctrine')->getManager();
                    $querybuilder = $em->createQueryBuilder()
                        ->select('t')
                        ->from('LanggasSisdikBundle:Sekolah', 't')
                        ->where('t.id = :sekolah')
                        ->setParameter('sekolah', $sekolah)
                    ;

                    $builder
                        ->add('sekolah', new EntityHiddenType($em), [
                            'required' => true,
                            'class' => 'LanggasSisdikBundle:Sekolah',
                            'data' => $sekolah->getId(),
                        ])
                    ;
                }
            }
        }

        $builder
            ->add('enabled', 'checkbox', [
                'label' => 'label.enabled',
                'required' => false,
                'widget_checkbox_label' => 'widget',
                'horizontal_input_wrapper_class' => 'col-sm-offset-4 col-sm-8 col-md-offset-4 col-md-7 col-lg-offset-3 col-lg-9',
            ])
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class' => 'Langgas\SisdikBundle\Entity\User',
            ])
        ;
    }

    public function getName()
    {
        return 'langgas_sisdikbundle_user';
    }
}