<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Hooks\Interfaces;

use OliverKlee\Seminars\Model\Registration;
use TYPO3\CMS\Core\Mail\MailMessage;

/**
 * This interface needs to be used for hooks concerning the back-end module.
 */
interface BackEndModule
{
    /**
     * Modifies the general e-mail sent via the back-end module.
     *
     * Note: This hook does not get called yet. It is just here so the interface is finalized.
     */
    public function modifyGeneralEmail(Registration $registration, MailMessage $eMail): void;

    /**
     * Modifies the confirmation e-mail sent via the back-end module.
     */
    public function modifyConfirmEmail(Registration $registration, MailMessage $eMail): void;

    /**
     * Modifies the cancelation e-mail sent via the back-end module.
     *
     * Note: This hook does not get called yet. It is just here so the interface is finalized.
     */
    public function modifyCancelEmail(Registration $registration, MailMessage $eMail): void;
}
