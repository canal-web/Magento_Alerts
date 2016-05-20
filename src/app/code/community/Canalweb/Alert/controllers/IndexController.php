<?php

/**
 * Configurateur index controller
 *
 * @category   Mage
 * @package    Mage_Contacts
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Canalweb_Alert_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Return models from a brand as JSON for the alert form.
     */
    public function modelsAction() {
        $brand = (int) Mage::app()->getRequest()->getParam('brand');

        $read = Mage::getSingleton('core/resource')->getConnection('core_read');

        $query = "SELECT * FROM alerte_modeles WHERE marque_id = :marque_id";

        $binds = array('marque_id' => $brand);

        $brands = $read->fetchAll($query, $binds);

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($brands));
    }

    public function saveAction() {
        // Add CSRF protection.
        if(!$this->_validateFormKey()) exit;

        // Get database API.
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');

        // Get POST data.
        $data = Mage::app()->getRequest()->getParams();

        // Fetch brand name since we get the ID from the form.
        $brandName = $read->fetchOne('SELECT nom FROM alerte_marques WHERE id = :marque_id', array(':marque_id' => $data['marque']));

        $binds = array(
            'marque' => $brandName,
            'modele' => $data['modele'],
            'email' => $data['mail'],
        );

        $alertExists = $read->fetchOne('SELECT COUNT(*) FROM alerte WHERE marque = :marque AND modele = :modele AND email = :email', $binds);

        if ($alertExists < 1) {
            $query = 'insert into alerte '
                   . '(marque, modele, email) values '
                   . '(:marque, :modele, :email)';

            $write->query($query, $binds);
            Mage::getSingleton('core/session')->addSuccess('Votre demande d\'alerte a bien été prise en compte, vous recevrez vos futures alertes à <em>' . $data['mail'] . '</em>.');
        }
        else {
            Mage::getSingleton('core/session')->addError('Cette alerte existe déjà dans notre base.');
        }

        $this->getResponse()->setRedirect(Mage::getUrl('/').'#content');
    }

    public function sendAction() {
        // Replaced by sendAlerts() from model.
    }

    public function unsubscribeAction() {
        // Get URL data.
        $data = Mage::app()->getRequest()->getParams();
        $id = (int) $data['id'];
        $email = $data['email'];

        // Get database API.
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');

        $alertExists = $read->fetchOne('SELECT COUNT(*) FROM alerte WHERE id = :id AND email = :email', array('id' => $id, 'email' => $email));

        if ($alertExists) {
            $write->query('DELETE FROM alerte WHERE id = :id', array('id' => $id));

            Mage::getSingleton('core/session')->addError('Votre demande de suppression a bien été prise en compte, vous ne recevrez plus d\'e-mail de notre part concernant cette alerte.');
        }
        else {
            Mage::getSingleton('core/session')->addError('Cette alerte n\'existe pas.');
        }

        $this->getResponse()->setRedirect(Mage::getUrl('/').'#content');
    }
}
