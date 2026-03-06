<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Banner Digital sp. z o.o.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\MailcowPasswordSync\AppInfo;

use OCA\MailcowPasswordSync\Listener\PasswordChangeListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\PasswordUpdatedEvent;

class Application extends App implements IBootstrap {

    public const APP_ID = 'mailcow_password_sync';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(
            PasswordUpdatedEvent::class,
            PasswordChangeListener::class
        );
    }

    public function boot(IBootContext $context): void {
    }
}
