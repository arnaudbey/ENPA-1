<?php

namespace Claroline\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Claroline\CoreBundle\Form\SelectResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Entity\Resource\Repository;
use Claroline\CoreBundle\Entity\Resource\ResourceInstance;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Form\DirectoryType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;

class ResourceController extends Controller
{
    public function indexAction()
    {
        $user = $this->get('security.context')->getToken()->getUser(); 
        $em = $this->getDoctrine()->getEntityManager();
        $formResource = $this->get('form.factory')->create(new SelectResourceType(), new ResourceType());
        $personnalWS = $user->getPersonnalWorkspace();
        $resources = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->getWSListableRootResource($personnalWS); 
        $resourcesType = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceType')->findAll();

        return $this->render(
            'ClarolineCoreBundle:Resource:index.html.twig', array('form_resource' => $formResource->createView(), 'resources' => $resources, 'id' => null, 'resourcesType' => $resourcesType)
        );
    }
    
    public function showResourceFormAction($id)
    {
        $request = $this->get('request');
        $form = $request->request->get('select_resource_form');
        $idType = $form['type'];
        $em = $this->getDoctrine()->getEntityManager();
        $resourceType = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceType')->find($idType);
        $rsrcServName = $this->findRsrcServ($resourceType);
        $rsrcServ = $this->get($rsrcServName);
        $form = $rsrcServ->getForm();

        return $this->render(
            'ClarolineCoreBundle:Resource:form_page.html.twig', array('form' => $form->createView(), 'id' => $id, 'type' => $resourceType->getType())
        );
              
        throw new \Exception("form error");
    }
    
        
    public function getFormAction($id, $type)
    {
        $resourceType = $this->getDoctrine()->getEntityManager()->getRepository("Claroline\CoreBundle\Entity\Resource\ResourceType")->findOneBy(array('type' => $type));
        $name = $this->findRsrcServ($resourceType);
        $rsrcServ = $this->get($name);
        $form = $rsrcServ->getForm();
        
        return $this->render(
            'ClarolineCoreBundle:Resource:generic_form.html.twig', array('form' => $form->createView(), 'id' => $id, 'type' =>$type)
        );
    }
    
    //TODO: check return type; la partie js doit savoir si on retourne du json ou pas pour réafficher (ou non le formulaire)
    //TODO: renommer idRepository en idWorkspace
    public function addAction($type, $id, $idRepository)
    {
        $request = $this->get('request');
        $user = $this->get('security.context')->getToken()->getUser();
        
        if(null == $idRepository)
        {
            $idRepository = $user->getPersonnalWorkspace()->getId();
        }
        
        $resourceType = $this->getDoctrine()->getEntityManager()->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceType')->findOneBy(array('type' => $type));
        $name = $this->findRsrcServ($resourceType);
        $form = $this->get($name)->getForm();
        $form->bindRequest($request);
        $em = $this->getDoctrine()->getEntityManager();
 
        if($form->isValid())
        {   
            $resource = $this->get($name)->add($form, $id, $user);
            if(null!=$resource)   
            {
                $ri = new ResourceInstance();
                $ri->setUser($user);
                $dir = $em->getRepository('ClarolineCoreBundle:Resource\ResourceInstance')->find($id);
                $ri->setParent($dir);
                $resourceType = $em->getRepository('ClarolineCoreBundle:Resource\ResourceType')->findOneBy(array('type' => $type));
                $ri->setResourceType($resourceType);        
                $rightManager = $this->get('claroline.security.right_manager');
                $ri->setCopy(false);  
                $workspace = $em->getRepository('Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace')->find($idRepository);
                $ri->setWorkspace($workspace);
                $ri->setResource($resource);
                $resource->addInstance();
                $em->persist($ri);
                $em->flush();
                $rightManager->addRight($ri, $user, MaskBuilder::MASK_OWNER);  
                
                if($request->isXmlHttpRequest())
                {
                    $content = '{"key":'.$ri->getId().', "name":"'.$ri->getResource()->getName().'", "type":"'.$ri->getResourceType()->getType().'"}';
                    $response = new Response($content);
                    $response->headers->set('Content-Type', 'application/json');  

                    return $response;
                } 
            }
            
            $route = $this->get('router')->generate("claro_resource_index");
       
            return new RedirectResponse($route);
        }
        else
        {
            if($request->isXmlHttpRequest())
            {
                return $this->render(
                    'ClarolineCoreBundle:Resource:generic_form.html.twig', array('form' => $form->createView(), 'id' => $id, 'type' =>$type)
                );
            }
            else
            {
                return $this->render(
                    'ClarolineCoreBundle:Resource:form_page.html.twig', array ('form' => $form->createView(), 'type' => $type, 'id' => $id)
                );
            }
        }
    }
    
    public function defaultClickAction($type, $id)
    {
          $resourceInstance = $this->getDoctrine()->getEntityManager()->getRepository(
              'Claroline\CoreBundle\Entity\Resource\ResourceInstance')->find($id);
          
          $securityContext = $this->get('security.context');
          
          if(false == $securityContext->isGranted('VIEW', $resourceInstance))
          {
               throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
          }
          else
          {
              if($type!="null")
              {
                  $resourceType = $this->getDoctrine()->getEntityManager()->getRepository(
                      'Claroline\CoreBundle\Entity\Resource\ResourceType')->findOneBy(array('type' => $type));
              }
              else
              {
                  $resourceType = $this->getDoctrine()->getEntityManager()->getRepository(
                      'Claroline\CoreBundle\Entity\Resource\AbstractResource')->find($id)->getResourceType();
              }
              $name = $this->findRsrcServ($resourceType);
              
              if($type!='directory')
              {    
                  $response = $this->get($name)->getDefaultAction($resourceInstance->getResource()->getId());
              }
              else
              {
                  $response = $this->get($name)->getDefaultAction($id);
              }
          }

        return $response;
    }
    
    /*
    public function deleteAction($id)
    {
       $request = $this->get('request');
       $em = $this->getDoctrine()->getEntityManager();
       $resourceInstance = $em->getRepository('ClarolineCoreBundle:Resource\ResourceInstance')->find($id);
       $securityContext = $this->get('security.context');
       
       if(false == $securityContext->isGranted('OWNER', $resourceInstance))
       {
           throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
       }
       else
       {    
            $resourceType = $resourceInstance->getResourceType();
            $name = $this->findRsrcServ($resourceType);
            
            if($resourceInstance->getResource()->getInstanceAmount() == 1)
            {
                $this->get($name)->delete($resourceInstance->getResource());
            }
            
            if($request->isXmlHttpRequest())
            {
                return new Response("delete");
            }

            $route = $this->get('router')->generate("claro_resource_index");

            return new RedirectResponse($route);
       }
    }*/
    
    public function openAction($id)
    {      
       $em = $this->getDoctrine()->getEntityManager(); 
       $resourceInstance = $em->getRepository('ClarolineCoreBundle:Resource\ResourceInstance')->find($id);
       $securityContext = $this->get('security.context');
       
       if(false == $securityContext->isGranted('VIEW', $resourceInstance))
       {
           throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
       }
       else
       {
           $resourceType = $resourceInstance->getResourceType();
           $name = $this->findRsrcServ($resourceType);
           $response = $this->get($name)->indexAction($resourceInstance);

           return $response;
       }
    }
    
    public function editAction($idResource, $idWorkspace, $options)
    {
        //resource copy
        if($options == 'copy')
        {
            $em = $this->getDoctrine()->getEntityManager();
            $resource = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')->find($idResource);;
            $newResource = $this->createResourceCopy($resource);
            $workspace = $em->getRepository('Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace')->find($idWorkspace);
            $repository = $workspace->getRepository();
            $repository->addResource($newResource);
            $newResource->setCopy(true);
            //acls
            $roleCollaborator = $workspace->getCollaboratorRole();
            $rightManager = $this->get('claroline.security.right_manager');
            $rightManager->addRight($newResource, $roleCollaborator, MaskBuilder::MASK_VIEW);
            //remove right on the old resource
            $repository->removeResource($resource);
            $repository->removeResource($resource);
            $rightManager->removeRight($resource, $roleCollaborator, MaskBuilder::MASK_VIEW);
            $em->flush();
            
            return new Response("copied");
        }
        else
        {
            //do sthg else
        }
        
        return new Response("shouldn't go there");
        
    }
    
    public function getJSONResourceNodeAction($id, $idRepository)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $workspace = $em->getRepository('Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace')->find($idRepository);
        $response = new Response();
              
        if($id==0)
        {
            $resourcesInstance = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->getWSListableRootResource($workspace);
            $root = new ResourceInstance();
            $rootDir = new Directory();
            $rootDir->setName('root');
            $root->setResource($rootDir);
            $root->setId(0);
            $directoryType = $em->getRepository('ClarolineCoreBundle:Resource\ResourceType')->findBy(array('type' => 'directory'));
            $root->setResourceType($directoryType[0]);
            
            foreach($resourcesInstance as $resourceInstance)
            {
                $root->addChildren($resourceInstance);
            }
            //must add root in an array for the json response
            $root = array( 0 => $root);
            
            $content = $this->renderView('ClarolineCoreBundle:Resource:dynatree_resource.json.twig', array('resources' => $root));
            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/json');      
        }
        else
        {
            $parent = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->find($id);
            $resourcesInstance = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->getListableChildren($parent);
            //ne fait plus que récupérer les resources du repository
            $content = $this->renderView('ClarolineCoreBundle:Resource:dynatree_resource.json.twig', array('resources' => $resourcesInstance));
            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/json');     
        }
        
        return $response;
    }
       
    public function moveResourceAction ($idChild, $idParent)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $parent = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->find($idParent);
        $child = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->find($idChild);
        $child->setParent($parent);
        $this->getDoctrine()->getEntityManager()->flush();
        
        return new Response("success");
    }
    
    public function addToWorkspaceAction($resourceId, $workspaceId, $option)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $workspace = $em->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->find($workspaceId); 
        $rightManager = $this->get('claroline.security.right_manager');
        $roleCollaborator = $workspace->getCollaboratorRole();
        
        if($option == 'ref' )
        {
            if($resourceId == 0)
            {/*
                $user = $this->get('security.context')->getToken()->getUser();
                $resources = $this->get('claroline.resource.manager')->getRootResourcesOfUser($user);

                foreach($resources as $resource)
                {
                    $workspace->addResource($resource);
                    $children = $resource->getChildren();
                    $rightManager->addRight($resource, $roleCollaborator, MaskBuilder::MASK_VIEW);

                    foreach($children as $child)
                    {
                        $workspace->addResource($child);
                        $rightManager->addRight($child, $roleCollaborator, MaskBuilder::MASK_VIEW);
                    }
                }    */       
            }
            else
            {
                $em = $this->getDoctrine()->getEntityManager();  
                $resourceInstance = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->find($resourceId);
                $resourceInstanceCopy = $this->copyReferenceResourceInstance($resourceInstance);
                $resourceInstanceCopy->setWorkspace($workspace);
                $em->persist($resourceInstanceCopy);
                $resourceInstance->getResource()->addInstance();
                $em->flush();                               
                $rightManager->addRight($resourceInstanceCopy, $roleCollaborator, MaskBuilder::MASK_VIEW);
                $this->setChildrenReferenceCopy($resourceInstance, $workspace, $resourceInstanceCopy);
            }  
            
            $em->flush();
        }
        else
        {
           if($resourceId == 0)
           {
               
           }
           else
           {
               /*
               $em = $this->getDoctrine()->getEntityManager();  
               $resourceInstance = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->find($resourceId);
               $name = $this->findRsrcServ($resourceInstance->getResourceType());  
               $user = $this->get('security.context')->getToken()->getUser();
               
               if('directory' == $resourceInstance->getResourceType())
               {
                   $this->get($name)->copy($resourceInstance);
               }
               else
               {
                   $newResource = $this->get($name)->copy($resourceInstance->getResource());
               }
                 */
               /*
               $rightManager->addRight($newResource, $user, MaskBuilder::MASK_OWNER);
               $rightManager->addRight($newResource, $roleCollaborator, MaskBuilder::MASK_VIEW);
               $children = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')->children($newResource, false);
                      
               foreach($children as $child)
               {
                   $rightManager->addRight($child, $roleCollaborator, MaskBuilder::MASK_VIEW);
                   $rightManager->addRight($newResource, $user, MaskBuilder::MASK_OWNER);
                   $repository->addResource($child);
               } 
               
               $em->flush();*/
           }
           return new Response("you're not trying to copy this are you ?"); 
        }
        
        return new Response("success");
    }
        
    public function removeFromWorkspaceAction($resourceId, $workspaceId)
    {
        $em = $this->getDoctrine()->getEntityManager();  
        $workspace = $em->getRepository('Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace')->find($workspaceId);
        $managerRole = $workspace->getManagerRole();
        
        if(false == $this->get('security.context')->isGranted($managerRole->getName()))
        {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
        }
 
        $resourceInstance = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->find($resourceId);
        $resourceType = $resourceInstance->getResourceType();
        $name = $this->findRsrcServ($resourceType);  
        $em->remove($resourceInstance);
        $resourceInstance->getResource()->removeInstance();
        
        if($resourceInstance->getResourceType()->getType()=='directory')
        {
            
            $this->get($name)->delete($resourceInstance);
        }
        else
        {
             $this->get($name)->delete($resourceInstance->getResource());
        }

        $em->flush();
        
        return new Response("success"); 
    }
   
    private function findRsrcServ(ResourceType $resourceType)
    {
        $services = $this->container->getParameter("resource.service.list");
        $names = array_keys($services);
        $serviceName = null;
        
        foreach($names as $name)
        {
            $type = $this->get($name)->getResourceType();
            
            if($type == $resourceType->getType())
            {
                $serviceName = $name;
            }
        }
        
        return $serviceName;
    }
    
    public function getResourcesTypeAction()
    {
        $resourcesType = $this->getDoctrine()->getEntityManager()->getRepository('ClarolineCoreBundle:Resource\ResourceType')->findAll();
        $content = $this->renderView('ClarolineCoreBundle:Resource:resource_type.json.twig', array('resourcesType' => $resourcesType));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json'); 
        
        return $response;   
    }
    
    private function copyReferenceResourceInstance(ResourceInstance $resourceInstance)
    {
        $ric = new ResourceInstance();
        $ric->setUser($this->get('security.context')->getToken()->getUser());
        $ric->setCopy(true);
        $ric->setWorkspace($resourceInstance->getWorkspace());
        $ric->setResource($resourceInstance->getResource());
        $ric->setResourceType($resourceInstance->getResourceType());
        $ric->setParent(null);
        
        return $ric;
    }
    
    private function setChildrenReferenceCopy($parentInstance, $workspace, $parentCopy)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $children = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceInstance')->children($parentInstance, true);
        $rightManager = $this->get('claroline.security.right_manager');
        $roleCollaborator = $workspace->getCollaboratorRole(); 
        
        foreach($children as $child)
        {
            $copy = $this->copyReferenceResourceInstance($child);
            $copy->setParent($parentCopy);
            $copy->setWorkspace($workspace);
            $em->persist($copy);
            $copy->getResource()->addInstance();
            $em->flush();
            $this->setChildrenReferenceCopy($child, $workspace, $copy);
            $rightManager->addRight($copy, $roleCollaborator, MaskBuilder::MASK_VIEW);
        }
    }
}
