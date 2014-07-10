<?php

namespace Langgas\SisdikBundle\Controller;
use Doctrine\DBAL\DBALException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Langgas\SisdikBundle\Entity\ImbalanPendaftaran;
use Langgas\SisdikBundle\Form\ImbalanPendaftaranType;
use Langgas\SisdikBundle\Form\SimpleTahunSearchType;
use Langgas\SisdikBundle\Entity\JenisImbalan;
use Langgas\SisdikBundle\Entity\Tahun;
use Langgas\SisdikBundle\Entity\Gelombang;
use Langgas\SisdikBundle\Entity\Sekolah;
use JMS\SecurityExtraBundle\Annotation\PreAuthorize;

/**
 * ImbalanPendaftaran controller.
 *
 * @Route("/rewardamount")
 * @PreAuthorize("hasAnyRole('ROLE_ADMIN', 'ROLE_BENDAHARA')")
 */
class ImbalanPendaftaranController extends Controller
{
    /**
     * Lists all ImbalanPendaftaran entities.
     *
     * @Route("/", name="rewardamount")
     * @Template()
     */
    public function indexAction() {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $searchform = $this->createForm(new SimpleTahunSearchType($this->container));

        $querybuilder = $em->createQueryBuilder()->select('t')
                ->from('LanggasSisdikBundle:ImbalanPendaftaran', 't')->leftJoin('t.tahun', 't2')
                ->leftJoin('t.gelombang', 't3')->leftJoin('t.jenisImbalan', 't4')
                ->where('t2.sekolah = :sekolah')->orderBy('t2.tahun', 'DESC')->addOrderBy('t3.urutan', 'ASC');
        $querybuilder->setParameter('sekolah', $sekolah->getId());

        $searchform->submit($this->getRequest());
        if ($searchform->isValid()) {
            $searchdata = $searchform->getData();

            if ($searchdata['tahun'] != '') {
                $querybuilder->andWhere('t2.id = :tahun');
                $querybuilder->setParameter('tahun', $searchdata['tahun']->getId());
            }
        }

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate($querybuilder, $this->getRequest()->query->get('page', 1));

        return array(
            'pagination' => $pagination, 'searchform' => $searchform->createView()
        );
    }

    /**
     * Finds and displays a ImbalanPendaftaran entity.
     *
     * @Route("/{id}/show", name="rewardamount_show")
     * @Template()
     */
    public function showAction($id) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('LanggasSisdikBundle:ImbalanPendaftaran')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity ImbalanPendaftaran tak ditemukan.');
        }

        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity' => $entity, 'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Displays a form to create a new ImbalanPendaftaran entity.
     *
     * @Route("/new", name="rewardamount_new")
     * @Template()
     */
    public function newAction() {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $entity = new ImbalanPendaftaran();
        $form = $this->createForm(new ImbalanPendaftaranType($this->container), $entity);

        return array(
            'entity' => $entity, 'form' => $form->createView(),
        );
    }

    /**
     * Creates a new ImbalanPendaftaran entity.
     *
     * @Route("/create", name="rewardamount_create")
     * @Method("POST")
     * @Template("LanggasSisdikBundle:ImbalanPendaftaran:new.html.twig")
     */
    public function createAction(Request $request) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $entity = new ImbalanPendaftaran();
        $form = $this->createForm(new ImbalanPendaftaranType($this->container), $entity);
        $form->submit($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            try {
                $em->persist($entity);
                $em->flush();

                $this->get('session')->getFlashBag()
                        ->add('success', $this->get('translator')->trans('flash.reward.amount.inserted'));

            } catch (DBALException $e) {
                $message = $this->get('translator')->trans('exception.unique.rewardamount');
                throw new DBALException($message);
            }

            return $this
                    ->redirect(
                            $this
                                    ->generateUrl('rewardamount_show',
                                            array(
                                                'id' => $entity->getId()
                                            )));
        }

        return array(
            'entity' => $entity, 'form' => $form->createView(),
        );
    }

    /**
     * Displays a form to edit an existing ImbalanPendaftaran entity.
     *
     * @Route("/{id}/edit", name="rewardamount_edit")
     * @Template()
     */
    public function editAction($id) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('LanggasSisdikBundle:ImbalanPendaftaran')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity ImbalanPendaftaran tak ditemukan.');
        }

        $editForm = $this->createForm(new ImbalanPendaftaranType($this->container), $entity);
        $deleteForm = $this->createDeleteForm($id);

        return array(
                'entity' => $entity, 'edit_form' => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing ImbalanPendaftaran entity.
     *
     * @Route("/{id}/update", name="rewardamount_update")
     * @Method("POST")
     * @Template("LanggasSisdikBundle:ImbalanPendaftaran:edit.html.twig")
     */
    public function updateAction(Request $request, $id) {
        $sekolah = $this->isRegisteredToSchool();
        $this->setCurrentMenu();

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('LanggasSisdikBundle:ImbalanPendaftaran')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Entity ImbalanPendaftaran tak ditemukan.');
        }

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createForm(new ImbalanPendaftaranType($this->container), $entity);
        $editForm->submit($request);

        if ($editForm->isValid()) {

            try {
                $em->persist($entity);
                $em->flush();

                $this->get('session')->getFlashBag()
                        ->add('success', $this->get('translator')->trans('flash.reward.amount.updated'));

            } catch (DBALException $e) {
                $message = $this->get('translator')->trans('exception.unique.rewardamount');
                throw new DBALException($message);
            }

            return $this
                    ->redirect(
                            $this
                                    ->generateUrl('rewardamount_edit',
                                            array(
                                                'id' => $id, 'page' => $this->getRequest()->get('page')
                                            )));
        }

        return array(
                'entity' => $entity, 'edit_form' => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Deletes a ImbalanPendaftaran entity.
     *
     * @Route("/{id}/delete", name="rewardamount_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request, $id) {
        $this->isRegisteredToSchool();

        $form = $this->createDeleteForm($id);
        $form->submit($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('LanggasSisdikBundle:ImbalanPendaftaran')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Entity ImbalanPendaftaran tak ditemukan.');
            }

            try {
                $em->remove($entity);
                $em->flush();

                $this->get('session')->getFlashBag()
                        ->add('success', $this->get('translator')->trans('flash.reward.amount.deleted'));

            } catch (DBALException $e) {
                $message = $this->get('translator')->trans('exception.delete.restrict');
                throw new DBALException($message);
            }
        } else {
            $this->get('session')->getFlashBag()
                    ->add('error', $this->get('translator')->trans('flash.reward.amount.fail.delete'));
        }

        return $this->redirect($this->generateUrl('rewardamount'));
    }

    private function createDeleteForm($id) {
        return $this->createFormBuilder(array(
                    'id' => $id
                ))->add('id', 'hidden')->getForm();
    }

    private function setCurrentMenu() {
        $menu = $this->container->get('langgas_sisdik.menu.main');
        $menu[$this->get('translator')->trans('headings.fee', array(), 'navigations')][$this->get('translator')->trans('links.reward.amount', array(), 'navigations')]->setCurrent(true);
    }

    private function isRegisteredToSchool() {
        $user = $this->getUser();
        $sekolah = $user->getSekolah();

        if (is_object($sekolah) && $sekolah instanceof Sekolah) {
            return $sekolah;
        } else if ($this->container->get('security.context')->isGranted('ROLE_SUPER_ADMIN')) {
            throw new AccessDeniedException($this->get('translator')->trans('exception.useadmin'));
        } else {
            throw new AccessDeniedException($this->get('translator')->trans('exception.registertoschool'));
        }
    }
}
