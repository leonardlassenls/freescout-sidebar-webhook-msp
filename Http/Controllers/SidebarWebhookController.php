<?php

namespace Modules\SidebarWebhook\Http\Controllers;

use App\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class SidebarWebhookController extends Controller
{
    /**
     * Edit ratings.
     * @return Response
     */
    public function mailboxSettings($id)
    {
        $mailbox = Mailbox::findOrFail($id);

        return view('sidebarwebhook::mailbox_settings', [
            'settings' => [
                'sidebarwebhook.url' => \Option::get('sidebarwebhook.url')[(string)$id] ?? '',
                'sidebarwebhook.secret' => \Option::get('sidebarwebhook.secret')[(string)$id] ?? '',
            ],
            'mailbox' => $mailbox
        ]);
    }

    public function mailboxSettingsSave($id, Request $request)
    {
        $mailbox = Mailbox::findOrFail($id);

        $settings = $request->settings ?: [];

        $urls = \Option::get('sidebarwebhook.url') ?: [];
        $secrets = \Option::get('sidebarwebhook.secret') ?: [];

        $urls[(string)$id] = $settings['sidebarwebhook.url'] ?? '';
        $secrets[(string)$id] = $settings['sidebarwebhook.secret'] ?? '';

        \Option::set('sidebarwebhook.url', $urls);
        \Option::set('sidebarwebhook.secret', $secrets);

        \Session::flash('flash_success_floating', __('Settings updated'));

        return redirect()->route('mailboxes.sidebarwebhook', ['id' => $id]);
    }

    public function handleAction(Request $request)
    {
        $conversationId = $request->input('conversationId')
            ?? $request->input('conversation_id')
            ?? null;
        $conversationId = $conversationId !== null ? (int) $conversationId : null;

        $statusId = $request->input('conversationStatus', $request->input('conversation_status'));
        $conversationStatus = $this->normalizeConversationStatus($statusId);
        $conversationStatusName = (string) $request->input('conversationStatusName', $request->input('conversation_status_name', ''));

        $mailboxId = $request->input('mailboxId', $request->input('mailbox_id'));
        $mailboxId = $mailboxId !== null ? (int) $mailboxId : '';
        $customerId = $request->input('customerId', $request->input('customer_id'));
        $customerId = $customerId !== null ? (int) $customerId : '';

        if (!$conversationId) {
            return response($this->renderSidebarMessage('Ticket-ID nicht übergeben'), 200)
                ->header('Content-Type', 'text/html');
        }

        $baseUrl = config('sidebar.msp_manager_url');
        if (!$baseUrl) {
            return response($this->renderSidebarMessage('MSP Manager URL ist nicht konfiguriert'), 200)
                ->header('Content-Type', 'text/html');
        }

        $sidebarAction = $request->input('sidebar_action', $request->input('action', 'render'));

        $payload = [
            'conversation_id' => $conversationId,
            'conversation_status' => $conversationStatus,
            'conversation_status_name' => $conversationStatusName,
            'conversationId' => $conversationId,
            'conversationStatus' => $conversationStatus,
            'conversationStatusName' => $conversationStatusName,
            'mailbox_id' => $mailboxId,
            'mailboxId' => $mailboxId,
            'customer_id' => $customerId,
            'customerId' => $customerId,
            'sidebar_action' => $sidebarAction,
            'product_id' => $request->input('product_id'),
            'ticket_id' => $request->input('ticket_id'),
            'ticket_product_id' => $request->input('ticket_product_id'),
            'conversationId' => $conversationId,
            'conversationStatus' => $conversationStatus,
            'conversationStatusName' => $conversationStatusName,
        ];

        \Log::debug('Sidebar webhook payload', [
            'conversationId' => $conversationId,
            'conversationStatus' => $conversationStatus,
        ]);

        try {
            $response = Http::asForm()
                ->timeout(2)
                ->accept('text/html')
                ->post($baseUrl, $payload);
        } catch (\Throwable $e) {
            \Log::warning('[SidebarWebhook] MSP call failed', [
                'error' => $e->getMessage(),
            ]);

            return response($this->renderSidebarMessage('MSP Manager nicht erreichbar'), 200)
                ->header('Content-Type', 'text/html');
        }

        if (!$response->ok()) {
            return response($this->renderSidebarMessage('MSP Manager nicht erreichbar'), 200)
                ->header('Content-Type', 'text/html');
        }

        $body = $this->transformSidebarHtml($response->body(), [
            'conversationId' => $conversationId,
            'conversationStatus' => $conversationStatus,
            'conversationStatusName' => $conversationStatusName,
            'mailboxId' => $mailboxId,
            'customerId' => $customerId,
        ]);

        return response($body, 200)
            ->header('Content-Type', 'text/html');
    }

    private function transformSidebarHtml($html, array $conversationFields = [])
    {
        if (!$html) {
            return $html;
        }

        $previousUseErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrappedHtml = '<div id="swh-wrapper">' . $html . '</div>';
        $dom->loadHTML(mb_convert_encoding($wrappedHtml, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[@data-sidebar-action]');
        $actionUrl = \Helper::getSubdirectory() . '/sidebar/action';

        if ($nodes instanceof \DOMNodeList) {
            $nodesArray = [];
            foreach ($nodes as $node) {
                $nodesArray[] = $node;
            }

            foreach ($nodesArray as $node) {
                $form = $dom->createElement('form');
                $form->setAttribute('method', 'post');
                $form->setAttribute('action', $actionUrl);
                $form->setAttribute('class', 'sidebar-action-form');

                $csrfInput = $dom->createElement('input');
                $csrfInput->setAttribute('type', 'hidden');
                $csrfInput->setAttribute('name', '_token');
                $csrfInput->setAttribute('value', csrf_token());
                $form->appendChild($csrfInput);

                $hiddenFields = [
                    'sidebar_action' => $node->getAttribute('data-sidebar-action'),
                    'ticket_id' => $node->getAttribute('data-ticket-id'),
                    'product_id' => $node->getAttribute('data-product-id'),
                    'ticket_product_id' => $node->getAttribute('data-ticket-product-id'),
                ];
                $hiddenFields = array_merge($hiddenFields, $conversationFields);

                foreach ($hiddenFields as $name => $value) {
                    $input = $dom->createElement('input');
                    $input->setAttribute('type', 'hidden');
                    $input->setAttribute('name', $name);
                    $input->setAttribute('value', $value);
                    $form->appendChild($input);
                }

                $button = $dom->createElement('button');
                $button->setAttribute('type', 'submit');

                $classAttr = $node->getAttribute('class');
                if ($classAttr) {
                    $button->setAttribute('class', $classAttr);
                }

                while ($node->firstChild) {
                    $button->appendChild($node->firstChild);
                }

                $form->appendChild($button);
                $node->parentNode->replaceChild($form, $node);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        $wrapper = $dom->getElementById('swh-wrapper');
        if (!$wrapper) {
            return $html;
        }

        $output = '';
        foreach ($wrapper->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }

    private function normalizeConversationStatus($statusId)
    {
        $statusMap = [
            1 => 'active',
            2 => 'pending',
            3 => 'closed',
        ];

        if ($statusId === null || $statusId === '') {
            return 'unknown';
        }

        if (is_numeric($statusId)) {
            $statusId = (int) $statusId;
            return $statusMap[$statusId] ?? 'unknown';
        }

        $statusId = (string) $statusId;
        if (in_array($statusId, $statusMap, true)) {
            return $statusId;
        }

        return 'unknown';
    }

    private function renderSidebarMessage($message)
    {
        return \View::make('sidebarwebhook::sidebar', [
            'message' => $message,
        ])->render();
    }
}
