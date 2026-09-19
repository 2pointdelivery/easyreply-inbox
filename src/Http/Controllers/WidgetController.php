<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Support\WidgetAuth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the embeddable widget: the `<script src=".../widget.js">` loader
 * and the iframe page it injects. Both are public and cacheable — the
 * inbox token is validated only when the iframe boots a chat session.
 */
class WidgetController
{
    public function loader(): Response
    {
        return response()
            ->view('shared-inbox::widget.loader')
            ->header('Content-Type', 'application/javascript; charset=UTF-8');
    }

    public function page(Request $request, WidgetAuth $auth): Response
    {
        $inbox = $auth->inbox((int) $request->input('inbox'));

        abort_unless($auth->tokenValid($inbox, $request->input('token')), 403, 'Invalid widget token.');

        return response()->view('shared-inbox::widget.page', [
            'inboxId' => $inbox->id,
            'token' => $request->input('token'),
            'title' => $request->input('title', 'Chat with us'),
            'color' => $this->safeColor($request->input('color')),
        ]);
    }

    protected function safeColor(mixed $color): string
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#4f46e5';
    }
}
