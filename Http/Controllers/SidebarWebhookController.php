<?php

namespace Modules\SidebarWebhook\Http\Controllers;

use App\Mailbox;
use App\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /**
     * Ajax controller.
     */
    public function ajax(Request $request)
    {
        $response = [
            'status' => 'error',
            'msg'    => '', // this is error message
        ];

        switch ($request->action) {

            case 'loadSidebar':
                // mailbox_id and customer_id are required.
                if (!$request->mailbox_id || !$request->conversation_id) {
                    $response['msg'] = 'Missing required parameters';
                    break;
                }

                try {
                    $mailbox = Mailbox::findOrFail($request->mailbox_id);
                    $conversation = Conversation::findOrFail($request->conversation_id);
                    $customer = $conversation->customer;
                } catch (\Exception $e) {
                    $response['msg'] = 'Invalid mailbox or customer';
                    break;
                }

                $url = \Option::get('sidebarwebhook.url')[(string)$mailbox->id] ?? '';
                $secret = \Option::get('sidebarwebhook.secret')[(string)$mailbox->id] ?? '';
                if (!$url) {
                    $response['msg'] = 'Webhook URL is not set';
                    break;
                }

                $conversationId = (int) $conversation->id;

                $payload = [
                    'customerId'          => $customer->id,
                    'customerEmail'       => $customer->getMainEmail(),
                    'customerEmails'      => $customer->emails->pluck('email')->toArray(),
                    'customerPhones'      => $customer->getPhones(),
                    'conversationId'      => $conversationId,
                    'conversation_id'     => $conversationId,
                    'conversationStatus'  => $conversation->status,
                    'conversation_status' => $conversation->status,
                    'conversationStatusName' => method_exists($conversation, 'getStatusName')
                        ? $conversation->getStatusName()
                        : null,
                    'conversationSubject' => $conversation->getSubject(),
                    'conversationType'    => $conversation->getTypeName(),
                    'mailboxId'           => $mailbox->id,
                    'secret'              => empty($secret) ? '' : $secret,
                ];

                try {
                    $client = new \GuzzleHttp\Client();
                    $result = $client->post($url, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'text/html',
                        ],
                        'body' => json_encode($payload),
                    ]);
                    $response['html'] = $this->transformSidebarHtml($result->getBody()->getContents());
                    $response['status'] = 'success';
                } catch (\Exception $e) {
                    $response['msg'] = 'Webhook error: ' . $e->getMessage();
                    break;
                }

                break;

            default:
                $response['msg'] = 'Unknown action';
                break;
        }

        if ($response['status'] == 'error' && empty($response['msg'])) {
            $response['msg'] = 'Unknown error occured';
        }

        return \Response::json($response);
    }

    public function handleAction(Request $request)
    {
        \Log::info('SIDEBAR ACTION', $request->all());

        $baseUrl = config('sidebar.msp_manager_url');
        if (!$baseUrl) {
            return response('<div>MSP Manager URL is not configured</div>', 500)
                ->header('Content-Type', 'text/html');
        }

        $endpoint = rtrim($baseUrl, '/') . '/webhook/freescout/sidebar';

        $payload = [
            'action' => $request->input('action'),
            'ticket_id' => $request->input('ticket_id'),
            'product_id' => $request->input('product_id'),
            'ticket_product_id' => $request->input('ticket_product_id'),
        ];

        try {
            switch ($request->input('action')) {
                case 'add_product':
                case 'remove_product':
                default:
                    $response = Http::asForm()
                        ->accept('text/html')
                        ->post($endpoint, $payload);
                    break;
            }
        } catch (\Exception $e) {
            return response('<div>Webhook error: ' . e($e->getMessage()) . '</div>', 500)
                ->header('Content-Type', 'text/html');
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', 'text/html');
    }

    private function transformSidebarHtml($html)
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
        $actionUrl = '/sidebar/action';

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
                    'action' => $node->getAttribute('data-sidebar-action'),
                    'ticket_id' => $node->getAttribute('data-ticket-id'),
                    'product_id' => $node->getAttribute('data-product-id'),
                    'ticket_product_id' => $node->getAttribute('data-ticket-product-id'),
                ];

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
}
