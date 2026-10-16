<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\ChatMessage;
use App\Services\AIService;

class ChatController
{
    public function index(): void
    {
        $adminId = (int) Session::getAdminId();
        Response::view('chat/index', [
            'messages' => ChatMessage::history($adminId, 50),
            'pageTitle' => 'AI Chat',
        ]);
    }

    public function send(): void
    {
        $adminId = (int) Session::getAdminId();
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($message === '') {
            if ($this->isAjax()) {
                Response::error('Empty message');
            }
            Session::flash('error', 'Empty message');
            Response::redirect('/chat');
        }

        ChatMessage::add($adminId, 'user', $message);
        $history = ChatMessage::history($adminId, 20);

        $ai = new AIService();
        $result = $ai->chat($message, $history);

        $reply = 'Sorry, I could not generate a reply. Check AI API keys.';
        if (!empty($result['success']) && !empty($result['data']['reply'])) {
            $reply = (string) $result['data']['reply'];
        } elseif (!empty($result['error'])) {
            $reply = 'Error: ' . $result['error'];
        }

        ChatMessage::add($adminId, 'assistant', $reply);

        if ($this->isAjax()) {
            Response::success(['reply' => $reply], 'ok');
        }
        Response::redirect('/chat');
    }

    public function clear(): void
    {
        ChatMessage::clear((int) Session::getAdminId());
        Session::flash('success', 'Chat cleared.');
        Response::redirect('/chat');
    }

    private function isAjax(): bool
    {
        return (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        );
    }
}
