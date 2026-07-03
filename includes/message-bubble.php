<?php
/**
 * Message Bubble Template (used as a reference for JS rendering)
 * This file documents the expected HTML structure for message bubbles.
 * The actual rendering is done in JavaScript by chat.js.
 * 
 * Expected data:
 * - $isMine (bool): Whether the message is sent by the current user
 * - $msg (array): Message data with id, sender_id, sender_name, sender_image, message_text, is_read, created_at, payload
 */

// This is a documentation/template file.
// Message bubbles are rendered client-side by chat.js _createMessageBubble()
// This file exists to document the expected structure.
?>
<!--
Message Bubble Structure:
========================

<div class="flex justify-end|justify-start" data-message-id="[ID]">
    <div class="flex items-end gap-2 max-w-[75%] [flex-row-reverse if mine]">
        [avatar if not mine]
        <div>
            <div class="[blue gradient if mine | gray if other] px-4 py-2.5 shadow-sm rounded-2xl rounded-br-md|rounded-bl-md">
                [file attachment if any]
                [message text]
            </div>
            <div class="flex items-center gap-1 mt-1 [justify-end if mine | justify-start]">
                <p class="text-[10px] text-gray-400">[time]</p>
                [read receipt if mine: check or check-double]
            </div>
        </div>
    </div>
</div>
-->
