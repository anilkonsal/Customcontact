<?php

/**
 * Contacts index controller
 *
 * @category   Tii
 * @package    Tii_Customcontact
 * @author     Vikas Bansal 
 * @created    June 18, 2014
 */
require_once Mage::getModuleDir('controllers', 'Mage_Contacts') . DS . 'IndexController.php';

class Tii_Customcontact_IndexController extends Mage_Contacts_IndexController {

    const XML_PATH_EMAIL_RECIPIENT = 'contacts/email/recipient_email';
    const XML_PATH_EMAIL_SENDER = 'contacts/email/sender_email_identity';
    const XML_PATH_EMAIL_TEMPLATE = 'contacts/email/email_template';
    const XML_PATH_ENABLED = 'contacts/contacts/enabled';

    public function preDispatch() {
        parent::preDispatch();

        if (!Mage::getStoreConfigFlag(self::XML_PATH_ENABLED)) {
            $this->norouteAction();
        }
    }

    public function indexAction() {
        $this->loadLayout();
        $this->getLayout()->getBlock('contactForm')
                ->setFormAction(Mage::getUrl('*/*/post'));

        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }

    public function postAction() {
        $post = $this->getRequest()->getPost();

        if ($post) {
            $translate = Mage::getSingleton('core/translate');
            /* @var $translate Mage_Core_Model_Translate */
            $translate->setTranslateInline(false);
            try {
                $postObject = new Varien_Object();
                $postObject->setData($post);

                $error = false;

                if (!Zend_Validate::is(trim($post['name']), 'NotEmpty')) {
                    $error = true;
                }

                if (!Zend_Validate::is(trim($post['comment']), 'NotEmpty')) {
                    $error = true;
                }

                if (!Zend_Validate::is(trim($post['email']), 'EmailAddress')) {
                    $error = true;
                }

                if (Zend_Validate::is(trim($post['hideit']), 'NotEmpty')) {
                    $error = true;
                }

                $path = Mage::getBaseDir('media') . DS . 'contacts';
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }

                $acceptable = array(
                    'image/jpeg',
                    'image/jpg',
                    'image/gif',
                    'image/png'
                );

                $mailTemplate = Mage::getModel('core/email_template');
                /* @var $mailTemplate Mage_Core_Model_Email_Template */
                $fname = '';
                for ($i = 0; $i < count($_FILES['attachment']['name']); $i++) {

                    if (in_array($_FILES['attachment']['type'][$i], $acceptable) && (isset($_FILES['attachment']['name'][$i])) && $_FILES['attachment']['name'][$i] != '') {
                        $tmpFilePath = $_FILES['attachment']['tmp_name'][$i];
                        if ($tmpFilePath != "") {
                            $fname = $_FILES['attachment']['name'][$i];
                            $fileExt = strtolower(substr(strrchr($fname, "."), 1));
                            $fileNamewoe = rtrim($fname, $fileExt);
                            $fname = preg_replace('/\s+', '', $fileNamewoe) . time() . '.' . $fileExt;
                            $newFilePath = $path . DS . $fname;
                            if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                                $attachmentFilePath = Mage::getBaseDir('media') . DS . 'contacts' . DS . $fname;
                                $at = $mailTemplate->getMail()->createAttachment(file_get_contents($attachmentFilePath));
                                $at->disposition = Zend_Mime::DISPOSITION_INLINE;
                                $at->encoding = Zend_Mime::ENCODING_BASE64;
                                $at->filename = $fname;
                            }
                        }
                    } else {
                        $error = true;
                        Mage::getSingleton('customer/session')->addError(Mage::helper('contacts')->__('Incorrect Image file format. Please, use image files only'));
                        $this->_redirect('*/*/');
                        return;
                    }
                }

                if ($error) {
                    throw new Exception();
                }

                $mailTemplate->setDesignConfig(array('area' => 'frontend'))
                        ->setReplyTo($post['email'])
                        ->sendTransactional(
                                Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE), Mage::getStoreConfig(self::XML_PATH_EMAIL_SENDER), Mage::getStoreConfig(self::XML_PATH_EMAIL_RECIPIENT), null, array('data' => $postObject)
                );

                if (!$mailTemplate->getSentSuccess()) {
                    throw new Exception();
                }

                $translate->setTranslateInline(true);

                Mage::getSingleton('customer/session')->addSuccess(Mage::helper('contacts')->__('Your inquiry was submitted and will be responded to as soon as possible. Thank you for contacting us.'));
                $this->_redirect('*/*/');

                return;
            } catch (Exception $e) {
                $translate->setTranslateInline(true);

                Mage::getSingleton('customer/session')->addError(Mage::helper('contacts')->__('Unable to submit your request. Please, try again later'));
                $this->_redirect('*/*/');
                return;
            }
        } else {
            $this->_redirect('*/*/');
        }
    }

}
