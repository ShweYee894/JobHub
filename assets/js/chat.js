/**
 * chat.js
 *
 * Client-side chat engine for the Freelancer Marketplace.
 * Handles room selection, history loading, SSE streaming, message sending,
 * emoji picker, file attachment, typing indicators, and unread badge updates.
 *
 * Usage:
 *   const chat = new Chat({ userId: 42, role: 'client', csrfToken: 'abc', userName: 'John' });
 *   chat.openRoom(5, conversationObject);
 */
class Chat {

    /**
     * @param {Object} options
     * @param {number} options.userId      - Current user ID
     * @param {string} options.role        - 'client' or 'freelancer'
     * @param {string} options.csrfToken   - CSRF token for POST requests
     * @param {string} options.userName    - Current user's display name
     * @param {string} [options.baseUrl]   - Base URL (default '/finalproject')
     */
    constructor(options = {}) {
        this.userId    = options.userId || 0;
        this.role      = options.role || 'freelancer';
        this.csrfToken = options.csrfToken || '';
        this.userName  = options.userName || '';
        this.baseUrl   = options.baseUrl || '/finalproject';

        // ── State ─────────────────────────────────────────────────────────
        this.selectedRoomId = null;
        this.lastMessageId  = 0;
        this.conversations  = [];
        this.allConversations = [];
        this.eventSource    = null;
        this.sending        = false;
        this.scrollLocked   = true;
        this.messageOffset  = 0;
        this.totalMessages  = 0;
        this.hasMoreHistory = true;
        this.loadingHistory = false;
        this._sseRetryDelay = 1000;
        this._sseMaxRetry   = 30000;
        this._dedupeSet     = new Set();
        this._typingTimeout = null;
        this._isTyping      = false;
        this._selectedFile  = null;
        this._markReadTimer = null;

        // ── DOM references ────────────────────────────────────────────────
        this.els = {
            roomsPanel:       document.getElementById('roomsPanel'),
            roomList:         document.getElementById('roomList'),
            roomListLoading:  document.getElementById('roomListLoading'),
            roomListEmpty:    document.getElementById('roomListEmpty'),
            chatArea:         document.getElementById('chatArea'),
            chatHeader:       document.getElementById('chatHeader'),
            chatHeaderAvatar: document.getElementById('chatHeaderAvatar'),
            chatHeaderName:   document.getElementById('chatHeaderName'),
            chatHeaderStatus: document.getElementById('chatHeaderStatus'),
            chatMessages:     document.getElementById('chatMessages'),
            emptyChatMsg:     document.getElementById('emptyChatMsg'),
            chatPlaceholder:  document.getElementById('chatPlaceholder'),
            chatInputArea:    document.getElementById('chatInputArea'),
            chatInput:        document.getElementById('chatInput'),
            chatSendBtn:      document.getElementById('chatSendBtn'),
            searchInput:      document.getElementById('conversationSearch'),
            backToList:       document.getElementById('backToList'),
            totalUnread:      document.getElementById('totalUnread'),
            attachBtn:        document.getElementById('attachBtn'),
            fileInput:        document.getElementById('fileInput'),
            emojiBtn:         document.getElementById('emojiBtn'),
            emojiPicker:      document.getElementById('emojiPicker'),
            emojiGrid:        document.getElementById('emojiGrid'),
            filePreview:      document.getElementById('filePreview'),
            filePreviewName:  document.getElementById('filePreviewName'),
            filePreviewSize:  document.getElementById('filePreviewSize'),
            fileRemoveBtn:    document.getElementById('fileRemoveBtn'),
            typingBar:        document.getElementById('typingBar'),
            typingText:       document.getElementById('typingText'),
        };

        this._initEmojiPicker();
        this._bindEvents();
        this.loadConversations();
    }


    /* ═══════════════════════════════════════════════════════════════════════
       EVENT BINDING
       ═══════════════════════════════════════════════════════════════════════ */

    _bindEvents() {
        // Send button
        if (this.els.chatSendBtn) {
            this.els.chatSendBtn.addEventListener('click', () => this.sendMessage());
        }

        // Enter to send, Shift+Enter for newline
        if (this.els.chatInput) {
            this.els.chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            this.els.chatInput.addEventListener('input', () => {
                this._autoResizeInput();
                this._emitTyping();
            });
        }

        // Search conversations
        if (this.els.searchInput) {
            this.els.searchInput.addEventListener('input', (e) => this._searchConversations(e.target.value));
        }

        // Back button (mobile)
        if (this.els.backToList) {
            this.els.backToList.addEventListener('click', () => this._showListOnMobile());
        }

        // Scroll detection
        if (this.els.chatMessages) {
            this.els.chatMessages.addEventListener('scroll', () => {
                const el = this.els.chatMessages;
                const distFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
                this.scrollLocked = distFromBottom < 100;

                if (el.scrollTop < 50 && this.hasMoreHistory && !this.loadingHistory) {
                    this._loadOlderMessages();
                }
            });
        }

        // Attachment button
        if (this.els.attachBtn && this.els.fileInput) {
            this.els.attachBtn.addEventListener('click', () => this.els.fileInput.click());
            this.els.fileInput.addEventListener('change', (e) => this._onFileSelected(e));
        }

        // Remove file preview
        if (this.els.fileRemoveBtn) {
            this.els.fileRemoveBtn.addEventListener('click', () => this._clearFile());
        }

        // Emoji button toggle
        if (this.els.emojiBtn) {
            this.els.emojiBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.els.emojiPicker.classList.toggle('hidden');
            });
        }

        // Close emoji picker on outside click
        document.addEventListener('click', (e) => {
            if (this.els.emojiPicker && !this.els.emojiPicker.classList.contains('hidden')) {
                if (!this.els.emojiPicker.contains(e.target) && e.target !== this.els.emojiBtn) {
                    this.els.emojiPicker.classList.add('hidden');
                }
            }
        });
    }


    /* ═══════════════════════════════════════════════════════════════════════
       EMOJI PICKER
       ═══════════════════════════════════════════════════════════════════════ */

    _initEmojiPicker() {
        if (!this.els.emojiGrid) return;

        const emojis = [
            '😀','😂','😍','🥰','😊','😎','🤔','😅','😢','🥺',
            '😤','😡','👍','👎','❤️','🔥','✅','🎉','💪','🙏',
            '👋','🤝','💯','⭐','🌟','💬','📎','📌','🔔','⏰',
            '📄','📁','✏️','📝','💻','🖥️','🎨','📊','📈','🗓️',
            '⬢','●','▲','◆','★','☆','⊕','⊗','⊙','◈',
            '←','→','↑','↓','↔','⇒','⇔','∴','∞','≈',
        ];

        this.els.emojiGrid.innerHTML = '';
        emojis.forEach(emoji => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-8 h-8 flex items-center justify-center text-lg hover:bg-gray-100 dark:hover:bg-slate-600 rounded transition-colors';
            btn.textContent = emoji;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this._insertEmoji(emoji);
            });
            this.els.emojiGrid.appendChild(btn);
        });
    }

    _insertEmoji(emoji) {
        if (!this.els.chatInput) return;
        const input = this.els.chatInput;
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const text = input.value;
        input.value = text.slice(0, start) + emoji + text.slice(end);
        input.selectionStart = input.selectionEnd = start + emoji.length;
        input.focus();
        this._autoResizeInput();
        if (this.els.emojiPicker) this.els.emojiPicker.classList.add('hidden');
    }


    /* ═══════════════════════════════════════════════════════════════════════
       FILE ATTACHMENT
       ═══════════════════════════════════════════════════════════════════════ */

    _onFileSelected(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Max 10MB
        if (file.size > 10 * 1024 * 1024) {
            this._showErrorToast('File too large (max 10MB)');
            this._clearFile();
            return;
        }

        this._selectedFile = file;

        if (this.els.filePreview) this.els.filePreview.classList.remove('hidden');
        if (this.els.filePreviewName) this.els.filePreviewName.textContent = file.name;
        if (this.els.filePreviewSize) {
            const size = file.size < 1024 ? file.size + ' B'
                : file.size < 1048576 ? (file.size / 1024).toFixed(1) + ' KB'
                : (file.size / 1048576).toFixed(1) + ' MB';
            this.els.filePreviewSize.textContent = size;
        }
    }

    _clearFile() {
        this._selectedFile = null;
        if (this.els.fileInput) this.els.fileInput.value = '';
        if (this.els.filePreview) this.els.filePreview.classList.add('hidden');
    }

    /**
     * Build the payload for a file attachment.
     * For now, we store the file metadata. Actual upload requires
     * a separate upload endpoint (future work).
     */
    _buildFilePayload(file) {
        return {
            path: 'assets/upload/chat/' + encodeURIComponent(file.name),
            name: file.name,
            type: file.type || 'application/octet-stream',
            size: file.size,
        };
    }


    /* ═══════════════════════════════════════════════════════════════════════
       CONVERSATIONS
       ═══════════════════════════════════════════════════════════════════════ */

    loadConversations() {
        fetch(`${this.baseUrl}/shared/chat/room_list.php`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.conversations) return;

            this.conversations    = data.conversations;
            this.allConversations = [...data.conversations];

            if (this.els.roomListLoading) this.els.roomListLoading.classList.add('hidden');

            if (this.conversations.length === 0) {
                if (this.els.roomListEmpty) this.els.roomListEmpty.classList.remove('hidden');
                return;
            }

            this._renderConversationList(this.conversations);
            this._updateTotalUnread();

            // Auto-open latest conversation
            if (this.conversations.length > 0 && !this.selectedRoomId) {
                const latest = this.conversations[0];
                if (latest && latest.room_id) {
                    this.openRoom(latest.room_id, latest);
                }
            }
        })
        .catch(err => {
            console.error('loadConversations error:', err);
            if (this.els.roomListLoading) this.els.roomListLoading.classList.add('hidden');
        });
    }

    _renderConversationList(list) {
        if (!this.els.roomList) return;
        this.els.roomList.querySelectorAll('.room-item').forEach(el => el.remove());
        list.forEach(conv => {
            this.els.roomList.appendChild(this._buildConversationItem(conv));
        });
    }

    _buildConversationItem(conv) {
        const div = document.createElement('div');
        div.className = 'room-item flex items-center gap-3 px-4 py-3 border-b border-gray-50 dark:border-slate-700/50 cursor-pointer transition-all duration-150 hover:bg-gray-50 dark:hover:bg-slate-700/50';
        div.dataset.roomId = conv.room_id || '';

        if (conv.room_id && conv.room_id == this.selectedRoomId) {
            div.classList.add('bg-blue-50', 'dark:bg-slate-700');
        }

        const other    = conv.other_user || {};
        const avatarUrl = other.image
            ? `${this.baseUrl}/${other.image}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(other.name)}&background=6366f1&color=fff&bold=true&size=44`;

        const preview   = conv.last_message ? this._esc(conv.last_message) : 'No messages yet';
        const timeStr   = conv.last_message_time ? this._relativeTime(conv.last_message_time) : '';
        const hasUnread = conv.unread_count > 0;

        div.innerHTML = `
            <div class="relative flex-shrink-0">
                <img src="${avatarUrl}" class="w-11 h-11 rounded-full object-cover border-2 border-gray-100 dark:border-slate-600" alt="">
                <span class="online-indicator absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-white dark:border-slate-800 ${other.is_online ? '' : 'hidden'}"></span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold ${hasUnread ? 'text-gray-900 dark:text-white' : 'text-gray-800 dark:text-gray-200'} truncate">${this._esc(other.name)}</p>
                    <span class="text-[10px] ${hasUnread ? 'text-blue-500 font-semibold' : 'text-gray-400 dark:text-gray-500'} flex-shrink-0">${timeStr}</span>
                </div>
                <p class="text-[11px] text-primary font-medium truncate mt-0.5">${this._esc(conv.job_title)}</p>
                <p class="text-xs ${hasUnread ? 'text-gray-700 dark:text-gray-300 font-medium' : 'text-gray-500 dark:text-gray-400'} truncate mt-0.5">${preview}</p>
            </div>
            ${hasUnread ? `<span class="w-5 h-5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">${conv.unread_count > 99 ? '99+' : conv.unread_count}</span>` : ''}
        `;

        if (conv.room_id) {
            div.addEventListener('click', () => this.openRoom(conv.room_id, conv));
        }

        return div;
    }

    _searchConversations(query) {
        const q = (query || '').toLowerCase().trim();
        if (!q) {
            this.conversations = [...this.allConversations];
        } else {
            this.conversations = this.allConversations.filter(c => {
                const other = c.other_user || {};
                return (other.name && other.name.toLowerCase().includes(q))
                    || (c.job_title && c.job_title.toLowerCase().includes(q))
                    || (c.last_message && c.last_message.toLowerCase().includes(q));
            });
        }
        this._renderConversationList(this.conversations);
    }

    _updateTotalUnread() {
        const total = this.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
        if (!this.els.totalUnread) return;
        if (total > 0) {
            this.els.totalUnread.textContent = total > 99 ? '99+' : total;
            this.els.totalUnread.classList.remove('hidden');
        } else {
            this.els.totalUnread.classList.add('hidden');
        }
    }


    /* ═══════════════════════════════════════════════════════════════════════
       OPEN ROOM
       ═══════════════════════════════════════════════════════════════════════ */

    openRoom(roomId, conv) {
        if (!roomId) return;
        if (this.selectedRoomId === roomId) return;

        // Stop typing in previous room
        if (this.selectedRoomId && this._isTyping) {
            this._sendTypingState(0);
        }

        this.disconnectSSE();

        this.selectedRoomId = roomId;
        this.lastMessageId  = 0;
        this.messageOffset  = 0;
        this.totalMessages  = 0;
        this.hasMoreHistory = true;
        this.loadingHistory = false;
        this._isTyping      = false;
        this._dedupeSet.clear();
        if (this._typingTimeout) clearTimeout(this._typingTimeout);
        if (this._markReadTimer) clearTimeout(this._markReadTimer);

        // Highlight active item
        document.querySelectorAll('.room-item').forEach(el => {
            const isActive = el.dataset.roomId == roomId;
            el.classList.toggle('bg-blue-50', isActive);
            el.classList.toggle('dark:bg-slate-700', isActive);
        });

        // Clear unread badge on this item
        const activeItem = document.querySelector(`.room-item[data-room-id="${roomId}"]`);
        if (activeItem) {
            const badge = activeItem.querySelector('.w-5.h-5.rounded-full');
            if (badge) badge.remove();
        }

        // Update header
        const other = conv ? (conv.other_user || {}) : {};
        const avatarUrl = other.image
            ? `${this.baseUrl}/${other.image}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(other.name || 'User')}&background=6366f1&color=fff&bold=true&size=40`;

        if (this.els.chatHeaderAvatar) {
            this.els.chatHeaderAvatar.src = avatarUrl;
            this.els.chatHeaderAvatar.alt = other.name || '';
        }
        if (this.els.chatHeaderName) this.els.chatHeaderName.textContent = other.name || 'User';

        if (other.is_online) {
            if (this.els.chatHeaderStatus) {
                this.els.chatHeaderStatus.textContent = 'Online';
                this.els.chatHeaderStatus.className   = 'text-[11px] text-green-500 font-medium';
            }
        } else {
            if (this.els.chatHeaderStatus) {
                this.els.chatHeaderStatus.textContent = other.last_seen ? this._lastSeen(other.last_seen) : 'Offline';
                this.els.chatHeaderStatus.className   = 'text-[11px] text-gray-400 font-medium';
            }
        }

        // Show chat panels
        if (this.els.chatPlaceholder) {
            this.els.chatPlaceholder.classList.add('hidden');
            this.els.chatPlaceholder.classList.remove('flex');
        }
        if (this.els.chatHeader) {
            this.els.chatHeader.classList.remove('hidden');
            this.els.chatHeader.classList.add('flex');
        }
        if (this.els.chatMessages) {
            this.els.chatMessages.classList.remove('hidden');
            this.els.chatMessages.classList.add('flex');
            this.els.chatMessages.innerHTML = '';
        }
        if (this.els.emptyChatMsg) this.els.emptyChatMsg.classList.add('hidden');
        if (this.els.chatInputArea) this.els.chatInputArea.classList.remove('hidden');

        this._showChatOnMobile();

        // Load history + connect SSE
        this.loadHistory(roomId);
        this.connectSSE(roomId);

        // Mark messages as read
        this._markRead(roomId);

        // Update conversation in local array
        if (conv) conv.unread_count = 0;
        this._updateTotalUnread();

        // Update URL without reload
        if (window.history && window.history.pushState) {
            window.history.pushState({ roomId }, '', `?room=${roomId}`);
        }
    }


    /* ═══════════════════════════════════════════════════════════════════════
       MARK READ
       ═══════════════════════════════════════════════════════════════════════ */

    _markRead(roomId) {
        const body = new URLSearchParams();
        body.append('csrf_token', this.csrfToken);
        body.append('room_id', roomId);

        fetch(`${this.baseUrl}/shared/chat/mark_read.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.marked > 0) {
                // Update unread badge
                const conv = this.conversations.find(c => c.room_id == roomId);
                if (conv) conv.unread_count = 0;
                this._updateTotalUnread();
            }
        })
        .catch(err => console.error('markRead error:', err));
    }

    /**
     * Debounced mark read — batches rapid-fire calls into one request
     * every 5 seconds. Avoids N+1 POST storm on SSE message bursts.
     */
    _markReadDebounced(roomId) {
        if (this._markReadTimer) clearTimeout(this._markReadTimer);
        this._markReadTimer = setTimeout(() => {
            this._markRead(roomId);
            this._markReadTimer = null;
        }, 5000);
    }


    /* ═══════════════════════════════════════════════════════════════════════
       TYPING INDICATOR (OUTGOING)
       ═══════════════════════════════════════════════════════════════════════ */

    _emitTyping() {
        if (!this.selectedRoomId) return;

        // Send "typing" state
        if (!this._isTyping) {
            this._isTyping = true;
            this._sendTypingState(1);
        }

        // Reset debounce — after 2s of no input, send "stopped typing"
        if (this._typingTimeout) clearTimeout(this._typingTimeout);
        this._typingTimeout = setTimeout(() => {
            this._isTyping = false;
            this._sendTypingState(0);
        }, 2000);
    }

    _sendTypingState(isTyping) {
        const body = new URLSearchParams();
        body.append('csrf_token', this.csrfToken);
        body.append('room_id', this.selectedRoomId);
        body.append('is_typing', isTyping);

        fetch(`${this.baseUrl}/shared/chat/set_typing.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
        .catch(err => console.error('setTyping error:', err));
    }


    /* ═══════════════════════════════════════════════════════════════════════
       LOAD HISTORY
       ═══════════════════════════════════════════════════════════════════════ */

    loadHistory(roomId) {
        if (this.els.chatMessages) this.els.chatMessages.innerHTML = '';
        this.messageOffset  = 0;
        this.hasMoreHistory = true;

        this._fetchHistory(roomId, 0, (messages, total) => {
            if (messages.length === 0) {
                if (this.els.emptyChatMsg) this.els.emptyChatMsg.classList.remove('hidden');
                if (this.els.chatMessages) this.els.chatMessages.classList.add('hidden');
            } else {
                if (this.els.emptyChatMsg) this.els.emptyChatMsg.classList.add('hidden');
                if (this.els.chatMessages) this.els.chatMessages.classList.remove('hidden');
                this._renderMessages(messages);
                this.lastMessageId = messages[messages.length - 1].id;
                this.messageOffset = messages.length;
                this.totalMessages = total;
                this.scrollToBottom(true);
            }
        });
    }

    _loadOlderMessages() {
        if (!this.selectedRoomId || !this.hasMoreHistory || this.loadingHistory) return;

        this.loadingHistory = true;
        const el = this.els.chatMessages;
        const prevScrollHeight = el ? el.scrollHeight : 0;

        this._fetchHistory(this.selectedRoomId, this.messageOffset, (messages, total) => {
            this.loadingHistory = false;

            if (messages.length === 0) {
                this.hasMoreHistory = false;
                return;
            }

            const frag = document.createDocumentFragment();
            let lastDate = null;

            const firstChild = el ? el.querySelector('[data-msg-id]') : null;
            if (firstChild) {
                const firstDate = firstChild.dataset.dateKey;
                if (firstDate) lastDate = firstDate;
            }

            messages.forEach(msg => {
                const msgDate = this._dateKey(msg.created_at);
                if (msgDate !== lastDate) {
                    frag.appendChild(this._createDateSeparator(msg.created_at));
                    lastDate = msgDate;
                }
                frag.appendChild(this._createMessageBubble(msg));
            });

            if (el) {
                el.insertBefore(frag, el.firstChild);
                const newScrollHeight = el.scrollHeight;
                el.scrollTop = newScrollHeight - prevScrollHeight;
            }

            this.messageOffset += messages.length;
            this.totalMessages = total;
            this.hasMoreHistory = (this.messageOffset < total);
        });
    }

    _fetchHistory(roomId, offset, callback) {
        fetch(`${this.baseUrl}/shared/chat/load_history.php?room_id=${roomId}&offset=${offset}&limit=50`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            callback(data.messages || [], data.total || 0);
        })
        .catch(err => { console.error('loadHistory fetch error:', err); callback([], 0); });
    }


    /* ═══════════════════════════════════════════════════════════════════════
       SSE STREAMING
       ═══════════════════════════════════════════════════════════════════════ */

    connectSSE(roomId) {
        this.disconnectSSE();
        this._sseRetryDelay = 1000;

        const url = `${this.baseUrl}/shared/chat/sse.php?room_id=${roomId}&last_id=${this.lastMessageId}`;
        this.eventSource = new EventSource(url);

        this.eventSource.addEventListener('message', (e) => {
            try {
                const msg = JSON.parse(e.data);

                if (this._dedupeSet.has(msg.id)) return;
                this._dedupeSet.add(msg.id);

                if (this._dedupeSet.size > 500) {
                    const arr = [...this._dedupeSet];
                    this._dedupeSet = new Set(arr.slice(-250));
                }

                if (msg.room_id == this.selectedRoomId) {
                    this.appendMessage(msg);

                    // Batch mark read — fires at most once per 5 seconds
                    this._markReadDebounced(this.selectedRoomId);

                    // Update conversation preview
                    this._updateConversationAfterSend(msg);
                }

                if (msg.id > this.lastMessageId) {
                    this.lastMessageId = msg.id;
                }

                this._sseRetryDelay = 1000;
            } catch (err) { console.warn('SSE message parse error:', err); }
        });

        this.eventSource.addEventListener('typing', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data.is_typing) {
                    this._showTypingIndicator(data.user_name || 'Someone');
                } else {
                    this._hideTypingIndicator();
                }
            } catch (err) { console.warn('SSE typing parse error:', err); }
        });

        this.eventSource.addEventListener('connected', () => {
            this._sseRetryDelay = 1000;
        });

        this.eventSource.addEventListener('heartbeat', () => {});

        this.eventSource.onerror = () => {
            this.eventSource.close();
            this.eventSource = null;

            if (this.selectedRoomId === roomId) {
                setTimeout(() => {
                    if (this.selectedRoomId === roomId) {
                        // Just reconnect SSE — don't reload history (avoids conversation flash)
                        this.connectSSE(roomId);
                    }
                }, this._sseRetryDelay);

                this._sseRetryDelay = Math.min(this._sseRetryDelay * 2, this._sseMaxRetry);
            }
        };
    }

    disconnectSSE() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }


    /* ═══════════════════════════════════════════════════════════════════════
       SEND MESSAGE
       ═══════════════════════════════════════════════════════════════════════ */

    sendMessage(text, payload) {
        if (!this.els.chatInput || !this.selectedRoomId) return;

        const messageText = (text || this.els.chatInput.value || '').trim();
        const filePayload = this._selectedFile ? this._buildFilePayload(this._selectedFile) : null;
        const finalPayload = payload || filePayload;

        if (!messageText && !finalPayload) return;
        if (this.sending) return;

        this.sending = true;
        if (this.els.chatSendBtn) this.els.chatSendBtn.disabled = true;

        // Stop typing indicator
        if (this._isTyping) {
            this._isTyping = false;
            this._sendTypingState(0);
            if (this._typingTimeout) clearTimeout(this._typingTimeout);
        }

        // Optimistic UI
        const optimisticMsg = {
            id:          'temp_' + Date.now(),
            sender_id:   this.userId,
            sender_name: this.userName || 'You',
            sender_image: null,
            message_text: messageText,
            is_read:     0,
            created_at:  new Date().toISOString().replace('T', ' ').slice(0, 19),
            payload:     finalPayload,
            pending:     true,
        };

        this.appendMessage(optimisticMsg);
        this.scrollLocked = true;
        this.scrollToBottom(true);

        // Clear input + file
        if (this.els.chatInput) {
            this.els.chatInput.value = '';
            this._autoResizeInput();
        }
        this._clearFile();

        // POST to server
        const body = new URLSearchParams();
        body.append('csrf_token', this.csrfToken);
        body.append('room_id', this.selectedRoomId);
        body.append('message_text', messageText);
        if (finalPayload) body.append('payload', JSON.stringify(finalPayload));

        fetch(`${this.baseUrl}/shared/chat/send_message.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
        .then(r => {
            if (!r.ok) {
                return r.text().then(text => {
                    console.error('send_message HTTP', r.status, text);
                    throw new Error(`HTTP ${r.status}`);
                });
            }
            return r.json();
        })
        .then(data => {
            if (data.success && data.message) {
                this._replaceMessage(optimisticMsg.id, data.message);
                this._updateConversationAfterSend(data.message);
            } else {
                this._removeOptimistic(optimisticMsg.id);
                this._showErrorToast(data.error || 'Failed to send message');
            }
        })
        .catch(err => {
            console.error('sendMessage error:', err);
            this._removeOptimistic(optimisticMsg.id);
            this._showErrorToast('Network error. Please try again.');
        })
        .finally(() => {
            this.sending = false;
            if (this.els.chatSendBtn) this.els.chatSendBtn.disabled = false;
        });
    }


    /* ═══════════════════════════════════════════════════════════════════════
       APPEND / RENDER MESSAGES
       ═══════════════════════════════════════════════════════════════════════ */

    appendMessage(msg) {
        if (!this.els.chatMessages) return;

        if (this.els.emptyChatMsg) this.els.emptyChatMsg.classList.add('hidden');
        this.els.chatMessages.classList.remove('hidden');
        this.els.chatMessages.classList.add('flex');

        if (msg.id && !msg.pending) {
            const exists = this.els.chatMessages.querySelector(`[data-msg-id="${msg.id}"]`);
            if (exists) return;
        }

        const msgDate = this._dateKey(msg.created_at);
        const lastChild = this.els.chatMessages.lastElementChild;
        const lastDateKey = lastChild ? (lastChild.dataset.dateKey || null) : null;
        if (lastDateKey !== msgDate) {
            this.els.chatMessages.appendChild(this._createDateSeparator(msg.created_at));
        }

        const bubble = this._createMessageBubble(msg);
        this.els.chatMessages.appendChild(bubble);

        if (msg.id && typeof msg.id === 'number') {
            this.lastMessageId = Math.max(this.lastMessageId, msg.id);
        }

        if (this.scrollLocked || msg.sender_id == this.userId) {
            this.scrollToBottom(true);
        }
    }

    _renderMessages(messages) {
        if (!this.els.chatMessages) return;
        const frag = document.createDocumentFragment();
        let lastDate = null;

        messages.forEach(msg => {
            const msgDate = this._dateKey(msg.created_at);
            if (msgDate !== lastDate) {
                frag.appendChild(this._createDateSeparator(msg.created_at));
                lastDate = msgDate;
            }
            frag.appendChild(this._createMessageBubble(msg));
        });

        this.els.chatMessages.appendChild(frag);
    }

    _createMessageBubble(msg) {
        const isMine  = parseInt(msg.sender_id) === this.userId;
        const div     = document.createElement('div');
        div.className = `flex ${isMine ? 'justify-end' : 'justify-start'}`;
        div.dataset.msgId = msg.id || '';
        div.dataset.dateKey = this._dateKey(msg.created_at);

        if (msg.pending) div.dataset.pending = 'true';

        const time = this._formatTime(msg.created_at);

        const avatarUrl = msg.sender_image
            ? `${this.baseUrl}/${msg.sender_image}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(msg.sender_name || '')}&background=6366f1&color=fff&bold=true&size=28`;

        let avatarHtml = '';
        if (!isMine) {
            avatarHtml = `<img src="${avatarUrl}" class="w-7 h-7 rounded-full object-cover flex-shrink-0" alt="">`;
        }

        const readCheck = isMine
            ? msg.is_read
                ? '<span class="text-blue-300 text-[10px] ml-1"><i class="fas fa-check-double"></i></span>'
                : '<span class="text-blue-300/60 text-[10px] ml-1"><i class="fas fa-check"></i></span>'
            : '';

        let contentHtml = '';
        if (msg.payload && msg.payload.path) {
            const fileIcon = this._fileIcon(msg.payload.type);
            const fileSize = msg.payload.size ? (msg.payload.size / 1024).toFixed(1) + ' KB' : '';
            contentHtml = `
                <a href="${this._esc(msg.payload.path)}" target="_blank"
                   class="flex items-center gap-2 ${isMine ? 'text-white/90' : 'text-gray-700 dark:text-gray-300'} hover:underline">
                    <i class="fas ${fileIcon} text-lg"></i>
                    <div class="min-w-0">
                        <p class="text-sm truncate max-w-[200px]">${this._esc(msg.payload.name || 'Attachment')}</p>
                        ${fileSize ? `<p class="text-[10px] opacity-70">${fileSize}</p>` : ''}
                    </div>
                </a>`;
        }
        if (msg.message_text) {
            contentHtml += `<p class="text-sm leading-relaxed whitespace-pre-line">${this._esc(msg.message_text)}</p>`;
        }
        if (!contentHtml) {
            contentHtml = '<p class="text-sm text-gray-400 italic">Empty message</p>';
        }

        const pendingSpinner = msg.pending
            ? '<span class="ml-1"><i class="fas fa-circle-notch fa-spin text-[10px] opacity-50"></i></span>'
            : '';

        div.innerHTML = `
            <div class="flex items-end gap-2 max-w-[75%] ${isMine ? 'flex-row-reverse' : ''}">
                ${avatarHtml}
                <div>
                    <div class="${
                        isMine
                            ? 'bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-2xl rounded-br-md'
                            : 'bg-gray-100 dark:bg-slate-700 text-gray-900 dark:text-gray-100 rounded-2xl rounded-bl-md'
                    } px-4 py-2.5 shadow-sm">
                        ${contentHtml}
                    </div>
                    <div class="flex items-center gap-1 mt-1 ${isMine ? 'justify-end' : 'justify-start'}">
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">${time}</p>
                        ${readCheck}
                        ${pendingSpinner}
                    </div>
                </div>
            </div>`;

        return div;
    }

    _createDateSeparator(dateStr) {
        const div = document.createElement('div');
        div.className = 'flex items-center gap-3 my-3';
        const label = this._formatDateLabel(dateStr);
        div.innerHTML = `
            <div class="flex-1 h-px bg-gray-200 dark:bg-slate-700"></div>
            <span class="text-[11px] font-medium text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-slate-800 px-3 py-1 rounded-full">${label}</span>
            <div class="flex-1 h-px bg-gray-200 dark:bg-slate-700"></div>`;
        return div;
    }


    /* ═══════════════════════════════════════════════════════════════════════
       OPTIMISTIC MESSAGE HANDLING
       ═══════════════════════════════════════════════════════════════════════ */

    _replaceMessage(tempId, realMsg) {
        if (!this.els.chatMessages) return;
        const tempEl = this.els.chatMessages.querySelector(`[data-msg-id="${tempId}"]`);
        if (!tempEl) return;
        const realEl = this._createMessageBubble(realMsg);
        tempEl.replaceWith(realEl);
    }

    _removeOptimistic(tempId) {
        if (!this.els.chatMessages) return;
        const el = this.els.chatMessages.querySelector(`[data-msg-id="${tempId}"]`);
        if (el) el.remove();
    }

    _updateConversationAfterSend(message) {
        const conv = this.conversations.find(c => c.room_id == this.selectedRoomId);
        if (conv) {
            conv.last_message      = message.message_text || (message.payload && message.payload.name) || '';
            conv.last_message_time = message.created_at;
        }

        this.conversations.sort((a, b) => {
            if (!a.last_message_time) return 1;
            if (!b.last_message_time) return -1;
            return new Date(b.last_message_time) - new Date(a.last_message_time);
        });
        this.allConversations = [...this.conversations];

        this._renderConversationList(this.conversations);

        document.querySelectorAll('.room-item').forEach(el => {
            if (el.dataset.roomId == this.selectedRoomId) {
                el.classList.add('bg-blue-50', 'dark:bg-slate-700');
            }
        });
    }


    /* ═══════════════════════════════════════════════════════════════════════
       TYPING INDICATOR (INCOMING)
       ═══════════════════════════════════════════════════════════════════════ */

    _showTypingIndicator(name) {
        if (!this.els.typingBar || !this.els.typingText) return;
        this.els.typingText.textContent = `${name} is typing...`;
        this.els.typingBar.classList.remove('hidden');
    }

    _hideTypingIndicator() {
        if (this.els.typingBar) this.els.typingBar.classList.add('hidden');
    }


    /* ═══════════════════════════════════════════════════════════════════════
       SCROLL
       ═══════════════════════════════════════════════════════════════════════ */

    scrollToBottom(instant = false) {
        if (!this.els.chatMessages) return;
        requestAnimationFrame(() => {
            this.els.chatMessages.scrollTo({
                top: this.els.chatMessages.scrollHeight,
                behavior: instant ? 'instant' : 'smooth',
            });
        });
    }


    /* ═══════════════════════════════════════════════════════════════════════
       MOBILE NAVIGATION
       ═══════════════════════════════════════════════════════════════════════ */

    _isDesktop() {
        return window.matchMedia('(min-width: 640px)').matches;
    }

    _showChatOnMobile() {
        if (this._isDesktop()) {
            // Desktop: show both panels side by side
            if (this.els.roomsPanel) {
                this.els.roomsPanel.classList.remove('hidden');
                this.els.roomsPanel.classList.add('flex');
            }
        } else {
            // Mobile: hide rooms panel, show chat area
            if (this.els.roomsPanel) {
                this.els.roomsPanel.classList.add('hidden');
                this.els.roomsPanel.classList.remove('flex');
            }
        }
        if (this.els.chatArea) {
            this.els.chatArea.classList.remove('hidden');
            this.els.chatArea.classList.add('flex');
        }
    }

    _showListOnMobile() {
        if (this._isDesktop()) {
            // Desktop: both panels stay visible
            if (this.els.chatArea) {
                this.els.chatArea.classList.remove('hidden');
                this.els.chatArea.classList.add('flex');
            }
            if (this.els.roomsPanel) {
                this.els.roomsPanel.classList.remove('hidden');
                this.els.roomsPanel.classList.add('flex');
            }
        } else {
            // Mobile: show rooms list, hide chat
            if (this.els.chatArea) {
                this.els.chatArea.classList.add('hidden');
                this.els.chatArea.classList.remove('flex');
            }
            if (this.els.roomsPanel) {
                this.els.roomsPanel.classList.remove('hidden');
                this.els.roomsPanel.classList.add('flex');
            }
        }
        this.disconnectSSE();
        this.selectedRoomId = null;

        if (window.history && window.history.pushState) {
            window.history.pushState({}, '', window.location.pathname);
        }
    }


    /* ═══════════════════════════════════════════════════════════════════════
       INPUT
       ═══════════════════════════════════════════════════════════════════════ */

    _autoResizeInput() {
        const input = this.els.chatInput;
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }


    /* ═══════════════════════════════════════════════════════════════════════
       ERROR TOAST
       ═══════════════════════════════════════════════════════════════════════ */

    _showErrorToast(message) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-5 py-3 bg-red-600 text-white text-sm font-medium rounded-xl shadow-lg flex items-center gap-2';
        toast.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${this._esc(message)}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }


    /* ═══════════════════════════════════════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════════════════════════════════════ */

    _esc(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, c => map[c]);
    }

    _formatTime(ts) {
        if (!ts) return '';
        const d = this._parseDate(ts);
        return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
    }

    _relativeTime(ts) {
        if (!ts) return '';
        const d      = this._parseDate(ts);
        const now    = new Date();
        const diffMs = now - d;
        const sec    = Math.floor(diffMs / 1000);
        const min    = Math.floor(sec / 60);
        const hr     = Math.floor(min / 60);
        const day    = Math.floor(hr / 24);

        if (sec < 60)  return 'now';
        if (min < 60)  return `${min}m`;
        if (hr < 24)   return `${hr}h`;
        if (day === 1)  return 'Yesterday';
        if (day < 7)   return `${day}d`;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    _lastSeen(ts) {
        if (!ts) return 'Offline';
        const d   = this._parseDate(ts);
        const now = new Date();
        const min = Math.floor((now - d) / 60000);
        const hr  = Math.floor(min / 60);
        const day = Math.floor(hr / 24);

        if (min < 1)  return 'Last seen just now';
        if (min < 60) return `Last seen ${min}m ago`;
        if (hr < 24)  return `Last seen ${hr}h ago`;
        if (day === 1) return 'Last seen yesterday';
        return `Last seen ${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
    }

    _formatDateLabel(ts) {
        const d   = this._parseDate(ts);
        const now = new Date();
        const yest = new Date();
        yest.setDate(yest.getDate() - 1);

        if (d.toDateString() === now.toDateString())     return 'Today';
        if (d.toDateString() === yest.toDateString())    return 'Yesterday';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    _dateKey(ts) {
        return this._parseDate(ts).toDateString();
    }

    _parseDate(ts) {
        if (!ts) return new Date();
        const str = ts.replace(' ', 'T');
        if (!str.includes('Z') && !str.includes('+')) return new Date(str + 'Z');
        return new Date(str);
    }

    _fileIcon(mimeType) {
        if (!mimeType) return 'fa-file';
        if (mimeType.includes('pdf'))  return 'fa-file-pdf text-red-400';
        if (mimeType.includes('word') || mimeType.includes('doc')) return 'fa-file-word text-blue-400';
        if (mimeType.includes('zip'))  return 'fa-file-zipper text-yellow-400';
        if (mimeType.includes('image')) return 'fa-file-image text-green-400';
        return 'fa-file text-gray-400';
    }
}
