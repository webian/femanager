<?php

declare(strict_types=1);

namespace In2code\Femanager\Event;

use In2code\Femanager\Domain\Service\SendMailService;
use TYPO3\CMS\Core\Mail\FluidEmail;

class AfterMailSendEvent
{
    /**
     * @var FluidEmail
     */
    private $email;
    /**
     * @var array
     */
    private $variables;
    /**
     * @var SendMailService
     */
    private $service;

    public function __construct(FluidEmail $email, array $variables, SendMailService $service)
    {
        $this->email = $email;
        $this->variables = $variables;
        $this->service = $service;
    }

    /**
     * @return FluidEmail
     */
    public function getEmail(): FluidEmail
    {
        return $this->email;
    }

    /**
     * @return SendMailService
     */
    public function getService(): SendMailService
    {
        return $this->service;
    }

    /**
     * @return array
     */
    public function getVariables(): array
    {
        return $this->variables;
    }
}
