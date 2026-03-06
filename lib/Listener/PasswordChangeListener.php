<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Banner Digital sp. z o.o.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\MailcowPasswordSync\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\User\Events\PasswordUpdatedEvent;
use Psr\Log\LoggerInterface;

/**
 * Listens for password changes in Nextcloud and syncs them to Mailcow.
 *
 * Mapping: NC username "jan" -> Mailcow mailbox "jan@najmuje.eu"
 *
 * @template-implements IEventListener<PasswordUpdatedEvent>
 */
class PasswordChangeListener implements IEventListener {

    private LoggerInterface $logger;
    private IConfig $config;

    public function __construct(LoggerInterface $logger, IConfig $config) {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function handle(Event $event): void {
        if (!($event instanceof PasswordUpdatedEvent)) {
            return;
        }

        $user = $event->getUser();
        $password = $event->getPassword();
        $uid = $user->getUID();

        // If the password is empty/null, we can't sync (e.g. recovery key rotation)
        if (empty($password)) {
            $this->logger->warning(
                'MailcowPasswordSync: No plaintext password available for user {uid}, skipping sync.',
                ['uid' => $uid, 'app' => 'mailcow_password_sync']
            );
            return;
        }

        // Read config
        $mailcowUrl = $this->config->getAppValue('mailcow_password_sync', 'mailcow_url', '');
        $apiKey     = $this->config->getAppValue('mailcow_password_sync', 'mailcow_api_key', '');
        $domain     = $this->config->getAppValue('mailcow_password_sync', 'mail_domain', 'najmuje.eu');

        if (empty($mailcowUrl) || empty($apiKey)) {
            $this->logger->error(
                'MailcowPasswordSync: mailcow_url or mailcow_api_key not configured. '
                . 'Run: occ config:app:set mailcow_password_sync mailcow_url --value="https://mail.najmuje.eu" '
                . '&& occ config:app:set mailcow_password_sync mailcow_api_key --value="YOUR-KEY"',
                ['app' => 'mailcow_password_sync']
            );
            return;
        }

        $mailbox = $uid . '@' . $domain;

        $this->syncPasswordToMailcow($mailcowUrl, $apiKey, $mailbox, $password);
    }

    private function syncPasswordToMailcow(
        string $mailcowUrl,
        string $apiKey,
        string $mailbox,
        string $password
    ): void {
        $url = rtrim($mailcowUrl, '/') . '/api/v1/edit/mailbox';

        $payload = json_encode([
            'items' => [$mailbox],
            'attr'  => [
                'password'  => $password,
                'password2' => $password,
            ],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
            ],
            // If Mailcow uses self-signed cert on internal network, uncomment:
            // CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->logger->error(
                'MailcowPasswordSync: cURL error for mailbox {mailbox}: {error}',
                ['mailbox' => $mailbox, 'error' => $curlError, 'app' => 'mailcow_password_sync']
            );
            return;
        }

        if ($httpCode === 200) {
            $this->logger->info(
                'MailcowPasswordSync: Password synced successfully for {mailbox}.',
                ['mailbox' => $mailbox, 'app' => 'mailcow_password_sync']
            );
        } else {
            $this->logger->error(
                'MailcowPasswordSync: Failed to sync password for {mailbox}. '
                . 'HTTP {code}, response: {response}',
                [
                    'mailbox'  => $mailbox,
                    'code'     => $httpCode,
                    'response' => $response,
                    'app'      => 'mailcow_password_sync',
                ]
            );
        }
    }
}
