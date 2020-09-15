<?php
declare(strict_types=1);
namespace In2code\Femanager\Domain\Service;

use In2code\Femanager\Event\AfterMailSendEvent;
use In2code\Femanager\Event\BeforeMailSendEvent;
use In2code\Femanager\Utility\ObjectUtility;
use In2code\Femanager\Utility\TemplateUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SendMailService
 */
class SendMailService
{

    /**
     * Content Object
     *
     * @var object
     */
    public $contentObject = null;

    /**
     * SignalSlot Dispatcher
     *
     * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected $signalSlotDispatcher;
    /**
     * @var Mailer
     */
    private $mailer;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * SendMailService constructor.
     */
    public function __construct(Mailer $mailer, EventDispatcherInterface $dispatcher)
    {
        $this->contentObject = ObjectUtility::getContentObject();
        $this->mailer = $mailer;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param array $variables
     * @return void
     */
    protected function contentObjectStart(array $variables)
    {
        if (!empty($variables['user']) && method_exists($variables['user'], '_getProperties')) {
            $this->contentObject->start($variables['user']->_getProperties());
        }
    }

    /**
     * Generate and send Email
     *
     * @param string $template Template file in Templates/Email/
     * @param array $receiver Combination of Email => Name
     * @param array $sender Combination of Email => Name
     * @param string $subject Mail subject
     * @param array $variables Variables for assignMultiple
     * @param array $typoScript Add TypoScript to overwrite values
     * @return bool mail was sent?
     */
    public function send(
        string $template,
        array $receiver,
        array $sender,
        string $subject,
        array $variables = [],
        array $typoScript = []
    ): bool {
        if (false === $this->isMailEnabled($typoScript, $receiver)) {
            return false;
        }

        $this->contentObjectStart($variables);
        /** @var FluidEmail $email */
        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $variables = $this->embedImages($variables, $typoScript, $email);
        $this->prepareMailObject($template, $receiver, $sender, $subject, $variables, $email);
        $this->overwriteEmailReceiver($typoScript, $email);
        $this->overwriteEmailSender($typoScript, $email);
        $this->setSubject($typoScript, $email);
        $this->setCc($typoScript, $email);
        $this->setPriority($typoScript, $email);
        $this->setAttachments($typoScript, $email);

        $this->dispatcher->dispatch(new BeforeMailSendEvent($email, $variables, $this));
        GeneralUtility::makeInstance(Mailer::class)->send($email);
        $this->dispatcher->dispatch(new AfterMailSendEvent($email, $variables, $this));

        return true;
    }

    /**
     * @param array $variables
     * @param array $typoScript
     * @param FluidEmail $email
     * @return array
     */
    protected function embedImages(array $variables, array $typoScript, FluidEmail $email): array
    {
        if ($this->contentObject->cObjGetSingle($typoScript['embedImage'], $typoScript['embedImage.'])) {
            $images = GeneralUtility::trimExplode(
                ',',
                $this->contentObject->cObjGetSingle($typoScript['embedImage'], $typoScript['embedImage.']),
                true
            );
            $imageVariables = [];
            foreach ($images as $image) {
                $imageVariables[] = $email->embedFromPath($image);
            }
            $variables = array_merge($variables, ['embedImages' => $imageVariables]);
        }
        return $variables;
    }

    /**
     * @param string $template
     * @param array $receiver
     * @param array $sender
     * @param string $subject
     * @param array $variables
     * @param FluidEmail $email
     * @return void
     */
    protected function prepareMailObject(
        string $template,
        array $receiver,
        array $sender,
        string $subject,
        array $variables,
        FluidEmail $email
    ) {
        foreach ($receiver as $address => $name) {
            $email->to(new Address($address, $name));
        }
        foreach ($sender as $address => $name) {
            $email->from(new Address($address, $name));
        }

        $email
            ->subject($subject)
            ->setTemplate($template)
            ->format('html')
            ->html('')// only HTML mail
            ->assignMultiple($variables);
    }

    /**
     * @param array $typoScript
     * @param FluidEmail $email
     * @return void
     */
    protected function overwriteEmailReceiver(array $typoScript, FluidEmail $email)
    {
        if ($this->contentObject->cObjGetSingle($typoScript['receiver.']['email'], $typoScript['receiver.']['email.'])
            && $this->contentObject->cObjGetSingle($typoScript['receiver.']['name'], $typoScript['receiver.']['name.'])
        ) {
            $emailAddress = $this->contentObject->cObjGetSingle(
                $typoScript['receiver.']['email'],
                $typoScript['receiver.']['email.']
            );
            $name = $this->contentObject->cObjGetSingle(
                $typoScript['receiver.']['name'],
                $typoScript['receiver.']['name.']
            );
            $email->to(new Address($emailAddress, $name));
        }
    }

    /**
     * @param array $typoScript
     * @param FluidEmail $email
     * @return void
     */
    protected function overwriteEmailSender(array $typoScript, FluidEmail $email)
    {
        if ($this->contentObject->cObjGetSingle($typoScript['sender.']['email'], $typoScript['sender.']['email.']) &&
            $this->contentObject->cObjGetSingle($typoScript['sender.']['name'], $typoScript['sender.']['name.'])
        ) {
            $emailAddress = $this->contentObject->cObjGetSingle(
                $typoScript['sender.']['email'],
                $typoScript['sender.']['email.']
            );
            $name = $this->contentObject->cObjGetSingle(
                $typoScript['sender.']['name'],
                $typoScript['sender.']['name.']
            );
            $email->from(new Address($emailAddress, $name));
        }
    }

    /**
     * @param array $typoScript
     * @param FluidEmail $email
     * @return void
     */
    protected function setSubject(array $typoScript, FluidEmail $email)
    {
        if ($this->contentObject->cObjGetSingle($typoScript['subject'], $typoScript['subject.'])) {
            $email->subject($this->contentObject->cObjGetSingle($typoScript['subject'], $typoScript['subject.']));
        }
    }

    /**
     * @param array $typoScript
     * @param FluidEmail $email
     * @return void
     */
    protected function setCc(array $typoScript, FluidEmail $email)
    {
        if ($this->contentObject->cObjGetSingle($typoScript['cc'], $typoScript['cc.'])) {
            $email->cc(
                new Address(
                    ...[$this->contentObject->cObjGetSingle($typoScript['cc'], $typoScript['cc.'])]
                )
            );
        }
    }

    /**
     * @param array $typoScript
     * @param FluidEmail $email
     * @return void
     */
    protected function setPriority(array $typoScript, FluidEmail $email)
    {
        if ($this->contentObject->cObjGetSingle($typoScript['priority'], $typoScript['priority.'])) {
            $email->priority((int)$this->contentObject->cObjGetSingle($typoScript['priority'], $typoScript['priority.']));
        }
    }

    /**
     * @param array $typoScript
     * @param FluidEmail $email
     * @return void
     */
    protected function setAttachments(array $typoScript, FluidEmail $email)
    {
        if ($this->contentObject->cObjGetSingle($typoScript['attachments'], $typoScript['attachments.'])) {
            $files = GeneralUtility::trimExplode(
                ',',
                $this->contentObject->cObjGetSingle($typoScript['attachments'], $typoScript['attachments.']),
                true
            );
            foreach ($files as $file) {
                $email->attachFromPath($file);
            }
        }
    }

    /**
     * Get path and filename for mail template
     *
     * @param string $fileName
     * @return string
     */
    protected function getRelativeEmailPathAndFilename($fileName)
    {
        return TemplateUtility::getTemplatePath('Email/' . ucfirst($fileName) . '.html');
    }

    /**
     * @param array $typoScript
     * @param array $receiver
     * @return bool
     */
    protected function isMailEnabled(array $typoScript, array $receiver): bool
    {
        return $this->contentObject->cObjGetSingle($typoScript['_enable'], $typoScript['_enable.'])
            && count($receiver) > 0;
    }
}
