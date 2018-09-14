<?php
namespace Product\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Product\Model\ProductTable;
use Product\Form\ProductForm;
use Product\Model\Product;
use Application\Service\MailSender;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Interop\Container\ContainerInterface;

class ProductController extends AbstractActionController
{
    private $table;
    private $formManager;

    public function __construct(ProductTable $object, $formManager) {
        $this->table = $object;
        $this->formManager = $formManager;
    }

    /**
     * function sendEmailAction
     *
     * @params POST[data]
     * 
     * @response JsonEncode
     */
    public function sendEmailAction(){
        if (!$this->getRequest()->isPost()){
            return new ViewModel([
                'products' => $this->table->fetchAll(),
            ]);
        }

        $request = $this->getRequest();
        $response = $this->getResponse();

        $data = $request->getPost();

        foreach($data['data'] as $p){
            $products[] = $this->table->getProduct($p['id']);
        }
        try{
            $mail = new MailSender();
            $res = $mail->sendMail('sender@sender.com', 'recepient@mail.com', 'subject', $products);
        }catch(\Exception $e){
            return $response->setContent(\Zend\Json\Json::encode('Error: Mail not configured, see Application/src/Service/MailSender.php'));
        }


        return $response->setContent(\Zend\Json\Json::encode($res));
        
    }


    public function indexAction(){
        // Grab the paginator from the ProductTable:
        $paginator = $this->table->fetchAll(true);

        // Set the current page to what has been passed in query string,
        // or to 1 if none is set, or the page is invalid:
        $page = (int) $this->params()->fromQuery('page', 1);
        $page = ($page < 1) ? 1 : $page;
        $paginator->setCurrentPageNumber($page);

        // Set the number of items per page to 10:
        $paginator->setItemCountPerPage(2);
        $flashMessages = $this->flashmessenger();
        return new ViewModel([
            'paginator' => $paginator,
            'messages' => $flashMessages->getSuccessMessages()
        ]);
    }

    public function addAction(){
     
        $this->formManager->get('submit')->setValue('Add');

        $request = $this->getRequest();

        if (! $request->isPost()) {
            return ['form' => $this->formManager];
        }


        $this->formManager->setData($request->getPost());
        
        if (! $this->formManager->isValid()) {
            return ['form' => $this->formManager];
        }
        // print_r($this->formManager->getData());
        
        $this->table->saveProduct($this->formManager->getData());
        
        $flashMessages = $this->flashmessenger();
        $flashMessages->addSuccessMessage('Product successfully saved!');

        return $this->redirect()->toRoute('product');
    }

    public function editAction(){
        $id = (int) $this->params()->fromRoute('id', 0);

        if (0 === $id) {
            return $this->redirect()->toRoute('product', ['action' => 'add']);
        }

        // Retrieve the product with the specified id. Doing so raises
        // an exception if the product is not found, which should result
        // in redirecting to the landing page.
        try {
            $product = $this->table->getProduct($id);
        } catch (\Exception $e) {
            return $this->redirect()->toRoute('product', ['action' => 'index']);
        }

        $form = new ProductForm();
        $form->bind($product);
        $form->get('submit')->setAttribute('value', 'Edit');

        $request = $this->getRequest();
        $viewData = ['id' => $id, 'form' => $form];

        if (! $request->isPost()) {
            return $viewData;
        }

    
        $form->setData($request->getPost());

        if (! $form->isValid()) {
            return $viewData;
        }
        
        $this->table->saveProduct($product);

        // Redirect to product list
        return $this->redirect()->toRoute('product', ['action' => 'index']);
    }

    public function deleteAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('product');
        }

        $request = $this->getRequest();
        if ($request->isPost()) {
            $del = $request->getPost('del', 'No');

            if ($del == 'Yes') {
                $id = (int) $request->getPost('id');
                $this->table->deleteProduct($id);
            }

            // Redirect to list of products
            return $this->redirect()->toRoute('product');
        }

        return [
            'id'    => $id,
            'product' => $this->table->getProduct($id),
        ];
    }

}