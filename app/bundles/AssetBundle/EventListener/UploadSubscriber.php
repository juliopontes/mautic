<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AssetBundle\EventListener;

use Mautic\AssetBundle\Entity\Asset;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Oneup\UploaderBundle\Event\PostUploadEvent;
use Oneup\UploaderBundle\Event\ValidationEvent;
use Oneup\UploaderBundle\Uploader\Exception\ValidationException;
use Oneup\UploaderBundle\UploadEvents;

/**
 * Class UploadSubscriber
 *
 * @package Mautic\AssetBundle\EventListener
 */
class UploadSubscriber extends CommonSubscriber
{
    /**
     * @param MauticFactory $factory
     */
    protected $factory;

    /**
     * @param \Mautic\CoreBundle\Translation\Translator $translator
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param MauticFactory $factory
     */
    public function __construct (MauticFactory $factory)
    {
        $this->factory    = $factory;
        $this->translator = $factory->getTranslator();
    }

    /**
     * @return array
     */
    static public function getSubscribedEvents ()
    {
        return array(
            UploadEvents::POST_UPLOAD => array('onPostUpload', 0),
            UploadEvents::VALIDATION  => array('onUploadValidation', 0)
        );
    }

    /**
     * Moves upladed file to temporary directory where it can be found later
     * and all uploaded files in there cleared. Also sets file name to the response.
     *
     * @param PostUploadEvent $event
     */
    public function onPostUpload (PostUploadEvent $event)
    {
        $request   = $event->getRequest()->request;
        $response  = $event->getResponse();
        $tempId    = $request->get('tempId');
        $file      = $event->getFile();
        $config    = $event->getConfig();
        $uploadDir = $config['storage']['directory'];
        $tmpDir    = $uploadDir . '/tmp/' . $tempId;

        // Move uploaded file to temporary folder
        $file->move($tmpDir);

        // Set resposnse data
        $response['state']       = 1;
        $response['tmpFileName'] = $file->getBasename();
    }

    /**
     * Validates file before upload
     *
     * @param ValidationEvent $event
     */
    public function onUploadValidation (ValidationEvent $event)
    {
        $file       = $event->getFile();
        $extensions = $this->factory->getParameter('allowed_extensions');
        $maxAllowed = $this->factory->getModel('asset')->getMaxUploadSize();
        $maxSize    = Asset::convertSizeToBytes($maxAllowed . 'M'); // max size is set in MB

        if ($file !== null) {
            if ($file->getSize() > $maxSize) {
                $message = $this->translator->trans('mautic.asset.asset.error.file.size', array(
                    '%fileSize%' => round($file->getSize() / 1048576, 2),
                    '%maxSize%'  => round($maxSize / 1048576, 2)
                ), 'validators');
                throw new ValidationException($message);
            }

            if (!in_array(strtolower($file->getExtension()), array_map('strtolower', $extensions))) {
                $message = $this->translator->trans('mautic.asset.asset.error.file.extension', array(
                    '%fileExtension%' => $file->getExtension(),
                    '%extensions%'    => implode(', ', $extensions)
                ), 'validators');
                throw new ValidationException($message);
            }
        }
    }
}
