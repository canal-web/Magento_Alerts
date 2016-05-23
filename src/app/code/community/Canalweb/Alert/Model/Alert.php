<?php

class Canalweb_Alert_Model_Alert extends Mage_Core_Model_Abstract
{

    /**
     * Send alerts for
     *
     * @return boolean TRUE if the file was successfully uploaded.
     */
    public function sendAlerts()
    {
        set_time_limit(0);

        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $cwHelper = Mage::helper('alert');

        $store = Mage::app()->getStore();

        // Get attribute values slug as keys.
        $marques = array_flip($cwHelper->getAttributeOptions('marque', true));
        $familles = array_flip($cwHelper->getAttributeOptions('famille', true));

        // Fetch all alerts.
        $alerts = $read->fetchAll("SELECT * FROM alerte");

        // Mage::getModel('catalog/product_url')->formatUrlKey($string);
        $productUrlModel = Mage::getModel('catalog/product_url');

        $inMarque = array();
        $inFamille = array();
        foreach ($alerts as $alert) {
            $inMarque[] = $marques[$productUrlModel->formatUrlKey($alert['marque'])];
            $inFamille[] = $familles[$productUrlModel->formatUrlKey($alert['modele'])];
        }


        $products = Mage::getResourceModel('catalog/product_collection');
        Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($products);
        $products->addAttributeToSelect('created_at')
             // ->addAttributeToFilter('created_at', array('gteq' => date('Y-m-d H:i:s', strtotime('-24 days'))))
            ->addAttributeToFilter('created_at', array('gteq' => date('Y-m-d H:i:s', strtotime('-24 hours'))))
            ->addAttributeToFilter('marque', array('in' => $inMarque))
            ->addAttributeToFilter('famille', array('in' => $inFamille))
            ->addAttributeToFilter('status', 1)
            ->joinField(
                'is_in_stock',
                'cataloginventory/stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            )
            ->addAttributeToFilter('is_in_stock', array('eq' => 1));
        ;

        // Count number of new cars by brand and model.
        $cars = array();
        foreach ($products as $product) {
            $cars[$product->getMarque()][$product->getFamille()] = isset($cars[$product->getMarque()][$product->getFamille()])
                ? $cars[$product->getMarque()][$product->getFamille()] + 1
                : 1;
        }

        $emailTemplate = Mage::getModel('core/email_template')->loadDefault('alert_email');

        // Init e-mail sender model (we're using Zend_Mail instead of magento's
        // implementation since there encoding issues).
        $mail = new Zend_Mail('UTF-8');
        $mail->setFrom(Mage::getStoreConfig('trans_email/ident_custom1/email'), Mage::getStoreConfig('general/store_information/name'));

        $mails_sent = 0;
        foreach ($alerts as $alert) {
            $marque = $marques[$productUrlModel->formatUrlKey($alert['marque'])];
            $famille = $familles[$productUrlModel->formatUrlKey($alert['modele'])];

            if (isset($cars[$marque][$famille])) {
                $count = $cars[$marque][$famille];

                // Create an array of variables to assign to template.
                $variables = array();
                $variables['path'] = Mage::getDesign()->getSkinUrl('images/alert_email');
                $variables['link'] = Mage::getUrl('/', array('_type' => Mage_Core_Model_Store::URL_TYPE_WEB)) . 'voitures-occasion/marque/' . $productUrlModel->formatUrlKey($alert['marque']) . '/modele/' . $productUrlModel->formatUrlKey($alert['modele']) . '.html';
                $variables['unsubscribe'] = Mage::getUrl('alert/index/unsubscribe', array('_type' => Mage_Core_Model_Store::URL_TYPE_WEB, 'id' => $alert['id'], 'email' => $alert['email']));
                $variables['content'] = ($count > 1)
                    ? 'Nous avons ' . $count . ' nouvelles occasions qui correspondent à votre alerte : '
                    : 'Nous avons ' . $count . ' nouvelle occasion qui correspond à votre alerte : ';
                $variables['content'] .= '<span style="font-weight:bold;">' . $alert['marque'] . ' ' . $alert['modele'] . '<span>';

                $html = $emailTemplate->getProcessedTemplate($variables);

                // Clear persistent values.
                $mail->clearSubject();
		        $mail->clearRecipients();

                $mail->addTo($alert['email'])
                     ->setBodyHtml($html)
                     ->setSubject(($count > 1)
                        ? 'De nouveaux véhicules correspondants à ' . $alert['marque'] . ' ' . $alert['modele'] . ' sur ' . $store->getFrontendName() . ' !'
                        : 'Un nouveau véhicule correspondant à ' . $alert['marque'] . ' ' . $alert['modele'] . ' sur ' . $store->getFrontendName() . ' !'
                    );

                try {
                    $mail->send();
                    $mails_sent++;
                }
                catch(Exception $error) {
                    Mage::getSingleton('core/session')->addError('Alert: ' . $error->getMessage());
                }
            }
        }

        echo $mails_sent . " mail(s) envoyé(s).\n";
    }

    public function importMarques()
    {
        $write = Mage::getSingleton("core/resource")->getConnection("core_write");

        // Start deleting brands and models.
        $write->query('TRUNCATE TABLE alerte_marques');
        $write->query('TRUNCATE TABLE alerte_modeles');

        $url = Mage::getBaseUrl( Mage_Core_Model_Store::URL_TYPE_MEDIA ) . '/modele.xml';
        $xml = simplexml_load_file($url);

        if (isset($xml->produit)) {
            foreach ($xml->produit as $_produit) {
                // Create an object from values of the SimpleXMLElement (since the behavior
                // of this class is weird).
                $produit = new stdClass;
                foreach ($_produit as $key => $value) {
                    $produit->$key = trim($value);
                }

                $marques[$produit->famille_produit][$produit->nom_produit] = $produit->nom_produit;
            }
        }

        foreach ($marques as $marque => $modeles) {
            // Concatenated with . for readability
            $query = "insert into alerte_marques "
                   . "(nom) values "
                   . "(:nom)";

            $binds = array(
                'nom' => $marque,
            );

            $write->query($query, $binds);
            $marque_id = $write->lastInsertId();

            foreach ($modeles as $modele) {
                $query = "insert into alerte_modeles"
                   . "(marque_id, nom) values "
                   . "(:marque_id, :nom)";

                $binds = array(
                    'nom' => $modele,
                    'marque_id' => $marque_id,
                );

                $write->query($query, $binds);
            }
        }
    }

}
