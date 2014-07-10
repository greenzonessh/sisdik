<?php

namespace Langgas\SisdikBundle\Controller;
use Doctrine\DBAL\DBALException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Langgas\SisdikBundle\Entity\TahunAkademik;
use Langgas\SisdikBundle\Form\TahunAkademikType;
use Langgas\SisdikBundle\Entity\Sekolah;
use JMS\SecurityExtraBundle\Annotation\PreAuthorize;

/**
 * TahunAkademik controller.
 *
 * @Route("/academicyear")
 * @PreAuthorize("hasRole('ROLE_KEPALA_SEKOLAH')")
 */
class TahunAkademikController extends Controller
{
    /**
     * Lists all TahunAkademik entities.
     *
     * @Route("/", name="academicyear")
     * @Template()
     */
    public function indexAction() {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        if (is_object($sekolah) && $sekolah instanceof Sekolah) {
            $querybuilder = $em->createQueryBuilder()->select('tahunakademik')
                    ->from('LanggasSisdikBundle:TahunAkademik', 'tahunakademik')
                    ->where('tahunakademik.sekolah = :sekolah')->orderBy('tahunakademik.urutan', 'DESC')
                    ->addOrderBy('tahunakademik.nama', 'DESC')->setParameter('sekolah', $sekolah->getId());
        }

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate($querybuilder, $this->getRequest()->query->get('page', 1));

        return array(
            'pagination' => $pagination
        );
    }

    /**
     * Activate a TahunAkademik entity, and deactivate the rests.
     *
     * @Route("/{id}/activate", name="academicyear_activate")
     */
    public function activateAction($id) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('LanggasSisdikBundle:TahunAkademik')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity TahunAkademik tak ditemukan.');
        }

        $query = $em->createQueryBuilder()->update('LanggasSisdikBundle:TahunAkademik', 't')
                ->set('t.aktif', '0')->where('t.sekolah = :sekolah')
                ->setParameter('sekolah', $sekolah->getId())->getQuery();
        $query->execute();

        $entity->setAktif(1);
        $em->persist($entity);
        $em->flush();

        return $this
                ->redirect(
                        $this
                                ->generateUrl('academicyear',
                                        array(
                                            'page' => $this->getRequest()->get('page')
                                        )));
    }

    /**
     * Finds and displays a TahunAkademik entity.
     *
     * @Route("/{id}/show", name="academicyear_show")
     * @Template()
     */
    public function showAction($id) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('LanggasSisdikBundle:TahunAkademik')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity TahunAkademik tak ditemukan.');
        }

        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity' => $entity, 'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Displays a form to create a new TahunAkademik entity.
     *
     * @Route("/new", name="academicyear_new")
     * @Template()
     */
    public function newAction() {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = new TahunAkademik();

        $qbe = $em->createQueryBuilder();
        $queryUrutan = $em->createQueryBuilder()->select($qbe->expr()->max('tahunAkademik.urutan'))
                ->from('LanggasSisdikBundle:TahunAkademik', 'tahunAkademik')
                ->where('tahunAkademik.sekolah = :sekolah')->setParameter('sekolah', $sekolah->getId());
        $nomorUrut = $queryUrutan->getQuery()->getSingleScalarResult();
        $nomorUrut = $nomorUrut === null ? 0 : $nomorUrut;
        $nomorUrut++;

        $entity->setUrutan($nomorUrut);

        $form = $this->createForm(new TahunAkademikType($this->container), $entity);

        return array(
            'entity' => $entity, 'form' => $form->createView(),
        );
    }

    /**
     * Creates a new TahunAkademik entity.
     *
     * @Route("/create", name="academicyear_create")
     * @Method("POST")
     * @Template("LanggasSisdikBundle:TahunAkademik:new.html.twig")
     */
    public function createAction(Request $request) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $entity = new TahunAkademik();
        $form = $this->createForm(new TahunAkademikType($this->container), $entity);
        $form->submit($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            try {
                $em->persist($entity);
                $em->flush();

                $this->get('session')->getFlashBag()
                        ->add('success',
                                $this->get('translator')
                                        ->trans('flash.data.academicyear.inserted',
                                                array(
                                                    '%year%' => $entity->getNama()
                                                )));

                return $this
                        ->redirect(
                                $this
                                        ->generateUrl('academicyear_show',
                                                array(
                                                    'id' => $entity->getId()
                                                )));
            } catch (DBALException $e) {
                $exception = $this->get('translator')->trans('exception.unique.year.school');
                throw new DBALException($exception);
            }
        }

        return array(
            'entity' => $entity, 'form' => $form->createView(),
        );
    }

    /**
     * Displays a form to edit an existing TahunAkademik entity.
     *
     * @Route("/{id}/edit", name="academicyear_edit")
     * @Template()
     */
    public function editAction($id) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('LanggasSisdikBundle:TahunAkademik')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity TahunAkademik tak ditemukan.');
        }

        $editForm = $this->createForm(new TahunAkademikType($this->container), $entity);
        $deleteForm = $this->createDeleteForm($id);

        return array(
                'entity' => $entity, 'edit_form' => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing TahunAkademik entity.
     *
     * @Route("/{id}/update", name="academicyear_update")
     * @Method("POST")
     * @Template("LanggasSisdikBundle:TahunAkademik:edit.html.twig")
     */
    public function updateAction(Request $request, $id) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('LanggasSisdikBundle:TahunAkademik')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity TahunAkademik tak ditemukan.');
        }

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createForm(new TahunAkademikType($this->container), $entity);
        $editForm->submit($request);

        if ($editForm->isValid()) {

            try {
                $em->persist($entity);
                $em->flush();

                $this->get('session')->getFlashBag()
                        ->add('success',
                                $this->get('translator')
                                        ->trans('flash.data.academicyear.updated',
                                                array(
                                                    '%year%' => $entity->getNama()
                                                )));

                return $this
                        ->redirect(
                                $this
                                        ->generateUrl('academicyear_edit',
                                                array(
                                                    'id' => $id, 'page' => $this->getRequest()->get('page')
                                                )));
            } catch (DBALException $e) {
                $exception = $this->get('translator')->trans('exception.unique.year.school');
                throw new DBALException($exception);
            }
        }

        return array(
                'entity' => $entity, 'edit_form' => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Deletes a TahunAkademik entity.
     *
     * @Route("/{id}/delete", name="academicyear_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request, $id) {
        $this->isRegisteredToSchool();

        $form = $this->createDeleteForm($id);
        $form->submit($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('LanggasSisdikBundle:TahunAkademik')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Entity TahunAkademik tak ditemukan.');
            }

            try {
                $em->remove($entity);
                $em->flush();

                $this->get('session')->getFlashBag()
                        ->add('success',
                                $this->get('translator')
                                        ->trans('flash.data.academicyear.deleted',
                                                array(
                                                    '%year%' => $entity->getNama()
                                                )));
            } catch (DBALException $e) {
                $message = $this->get('translator')->trans('exception.delete.restrict');
                throw new DBALException($message);
            }
        } else {
            $this->get('session')->getFlashBag()
                    ->add('error', $this->get('translator')->trans('flash.data.academicyear.fail.delete'));
        }

        return $this
                ->redirect(
                        $this
                                ->generateUrl('academicyear',
                                        array(
                                            'page' => $this->getRequest()->get('page')
                                        )));
    }

    private function createDeleteForm($id) {
        return $this->createFormBuilder(array(
                    'id' => $id
                ))->add('id', 'hidden')->getForm();
    }

    private function setCurrentMenu() {
        $menu = $this->container->get('langgas_sisdik.menu.main');
        $menu[$this->get('translator')->trans('headings.setting', array(), 'navigations')][$this->get('translator')->trans('links.academicyear', array(), 'navigations')]->setCurrent(true);
    }

    private function isRegisteredToSchool() {
        $user = $this->getUser();
        $sekolah = $user->getSekolah();

        if (is_object($sekolah) && $sekolah instanceof Sekolah) {
            return $sekolah;
        } elseif ($this->container->get('security.context')->isGranted('ROLE_SUPER_ADMIN')) {
            throw new AccessDeniedException($this->get('translator')->trans('exception.useadmin'));
        } else {
            throw new AccessDeniedException($this->get('translator')->trans('exception.registertoschool'));
        }
    }
}
