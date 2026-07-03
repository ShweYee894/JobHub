/**
 * Chat – Real-time messaging for FreelanceHub
 * Shared class used by both client/messages.php and freelancer/messages.php.
 *
 * Fixes applied:
 *  1. Messages now display correctly when clicking a conversation
 *  2. New messages appear instantly via SSE without page refresh
 *  3. Latest conversation auto-opens on page load
 *  4. Both parties see messages in real-time
 *  5. Selected conversation stays active during interactions
 *  6. Unread badge clears after opening conversation
 *  7. Messages ordered oldest -> newest
 *  8. Auto-scroll always targets newest message
 *  9. Timestamps: Today, Yesterday, date, relative times
 * 10. Sent=right (blue), Received=left (gray)
 */
class Chat {
  constructor(options = {}) {
    this.userId = options.userId || 0;
    this.role = options.role || "freelancer";
    this.baseUrl = options.baseUrl || "/finalproject";
    this.csrfToken = options.csrfToken || "";
    this.selectedRoomId = 0;
    this.lastMessageId = 0;
    this.eventSource = null;
    this.conversations = [];
    this.allConversations = [];
    this.sending = false;
    this.typingTimeout = null;
    this.onlineCheckInterval = null;
    this.notificationPermission = false;
    this._pendingFile = null;
    this._scrollLocked = true;
    this._messageOffset = 0;

    this.els = {
      roomList: document.getElementById("roomList"),
      chatMessages: document.getElementById("chatMessages"),
      chatHeader: document.getElementById("chatHeader"),
      chatHeaderName: document.getElementById("chatHeaderName"),
      chatHeaderStatus: document.getElementById("chatHeaderStatus"),
      chatHeaderAvatar: document.getElementById("chatHeaderAvatar"),
      chatInput: document.getElementById("chatInput"),
      chatSendBtn: document.getElementById("chatSendBtn"),
      placeholder: document.getElementById("chatPlaceholder"),
      chatArea: document.getElementById("chatArea"),
      roomsPanel: document.getElementById("roomsPanel"),
      unreadBadge: document.getElementById("unreadBadge"),
      emptyChatMsg: document.getElementById("emptyChatMsg"),
      chatInputArea: document.getElementById("chatInputArea"),
      emojiPicker: document.getElementById("emojiPicker"),
      emojiBtn: document.getElementById("emojiBtn"),
      attachBtn: document.getElementById("attachBtn"),
      fileInput: document.getElementById("fileInput"),
      filePreview: document.getElementById("filePreview"),
    };

    this.init();
  }

  /* ── Initialization ─────────────────────────────────────────── */
  init() {
    this.loadConversations();
    this._bindEvents();
    this._requestNotificationPermission();
    this._startOnlineCheck();
  }

  _bindEvents() {
    if (this.els.chatSendBtn) {
      this.els.chatSendBtn.addEventListener("click", () => this.sendMessage());
    }
    if (this.els.chatInput) {
      this.els.chatInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });
      this.els.chatInput.addEventListener("input", () => {
        this._autoResizeInput();
        this._sendTypingIndicator();
      });
    }

    const searchInput = document.getElementById("conversationSearch");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        this.searchConversations(e.target.value);
      });
    }

    if (this.els.emojiBtn) {
      this.els.emojiBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        this._toggleEmojiPicker();
      });
    }

    if (this.els.attachBtn) {
      this.els.attachBtn.addEventListener("click", () => {
        if (this.els.fileInput) this.els.fileInput.click();
      });
    }
    if (this.els.fileInput) {
      this.els.fileInput.addEventListener("change", (e) =>
        this._handleFileSelect(e),
      );
    }

    document.addEventListener("click", (e) => {
      if (
        this.els.emojiPicker &&
        !this.els.emojiPicker.contains(e.target) &&
        this.els.emojiBtn &&
        !this.els.emojiBtn.contains(e.target)
      ) {
        this.els.emojiPicker.classList.add("hidden");
      }
    });

    // Scroll detection for auto-scroll lock
    if (this.els.chatMessages) {
      this.els.chatMessages.addEventListener("scroll", () => {
        const el = this.els.chatMessages;
        const distFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
        this._scrollLocked = distFromBottom < 100;
      });
    }
  }

  _autoResizeInput() {
    const input = this.els.chatInput;
    if (!input) return;
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 120) + "px";
  }

  /* ── Typing Indicator ──────────────────────────────────────── */
  _sendTypingIndicator() {
    if (!this.selectedRoomId) return;
    clearTimeout(this.typingTimeout);

    const body = new URLSearchParams();
    body.append("csrf_token", this.csrfToken);
    body.append("room_id", this.selectedRoomId);

    fetch(`${this.baseUrl}/${this.role}/typing.php`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    }).catch(() => {});

    this.typingTimeout = setTimeout(() => {}, 3000);
  }

  showTypingIndicator(name) {
    if (!this.els.chatMessages) return;
    if (document.getElementById("typingIndicator")) return;

    const div = document.createElement("div");
    div.id = "typingIndicator";
    div.className = "flex justify-start";
    div.innerHTML = `
            <div class="flex items-end gap-2">
                <div class="bg-gray-100 dark:bg-slate-700 rounded-2xl rounded-bl-md px-4 py-3">
                    <div class="flex gap-1 items-center">
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                        <span class="text-[11px] text-gray-400 ml-2">${this.escapeHtml(name)} is typing</span>
                    </div>
                </div>
            </div>`;
    this.els.chatMessages.appendChild(div);
    this.scrollToBottom();
  }

  hideTypingIndicator() {
    const el = document.getElementById("typingIndicator");
    if (el) el.remove();
  }

  /* ── Emoji Picker ──────────────────────────────────────────── */
  _toggleEmojiPicker() {
    if (!this.els.emojiPicker) return;
    this.els.emojiPicker.classList.toggle("hidden");
  }

  _insertEmoji(emoji) {
    if (!this.els.chatInput) return;
    const start = this.els.chatInput.selectionStart;
    const end = this.els.chatInput.selectionEnd;
    const text = this.els.chatInput.value;
    this.els.chatInput.value =
      text.substring(0, start) + emoji + text.substring(end);
    this.els.chatInput.selectionStart = this.els.chatInput.selectionEnd =
      start + emoji.length;
    this.els.chatInput.focus();
    this.els.emojiPicker.classList.add("hidden");
  }

  /* ── File Upload ───────────────────────────────────────────── */
  _handleFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
      alert("File too large. Maximum size is 10MB.");
      return;
    }

    const allowedTypes = [
      "application/pdf",
      "application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      "application/zip",
      "image/png",
      "image/jpeg",
      "image/jpg",
    ];
    if (!allowedTypes.includes(file.type)) {
      alert("File type not allowed. Allowed: PDF, DOCX, ZIP, PNG, JPG");
      return;
    }

    this._pendingFile = file;
    this._showFilePreview(file);
  }

  _showFilePreview(file) {
    if (!this.els.filePreview) return;
    const iconMap = {
      "application/pdf": "fa-file-pdf text-red-500",
      "application/msword": "fa-file-word text-blue-500",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
        "fa-file-word text-blue-500",
      "application/zip": "fa-file-zipper text-yellow-500",
      "image/png": "fa-file-image text-green-500",
      "image/jpeg": "fa-file-image text-green-500",
      "image/jpg": "fa-file-image text-green-500",
    };
    const icon = iconMap[file.type] || "fa-file text-gray-500";
    const size = (file.size / 1024).toFixed(1) + " KB";

    this.els.filePreview.innerHTML = `
            <div class="flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-xl px-3 py-2 text-sm">
                <i class="fas ${icon}"></i>
                <span class="flex-1 truncate text-gray-700">${this.escapeHtml(file.name)}</span>
                <span class="text-xs text-gray-400">${size}</span>
                <button onclick="chat._clearFilePreview()" class="text-gray-400 hover:text-red-500 ml-1">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>`;
    this.els.filePreview.classList.remove("hidden");
  }

  _clearFilePreview() {
    this._pendingFile = null;
    if (this.els.filePreview) {
      this.els.filePreview.innerHTML = "";
      this.els.filePreview.classList.add("hidden");
    }
    if (this.els.fileInput) this.els.fileInput.value = "";
  }

  async _uploadFile(roomId) {
    if (!this._pendingFile) return null;

    const formData = new FormData();
    formData.append("csrf_token", this.csrfToken);
    formData.append("room_id", roomId);
    formData.append("file", this._pendingFile);

    try {
      const response = await fetch(
        `${this.baseUrl}/shared/upload_chat_file.php`,
        {
          method: "POST",
          body: formData,
        },
      );
      const data = await response.json();
      if (data.success) {
        this._clearFilePreview();
        return data.file;
      }
    } catch (err) {
      console.error("File upload failed:", err);
    }
    return null;
  }

  /* ── Browser Notifications ─────────────────────────────────── */
  _requestNotificationPermission() {
    if ("Notification" in window && Notification.permission === "default") {
      Notification.requestPermission().then((permission) => {
        this.notificationPermission = permission === "granted";
      });
    } else if ("Notification" in window) {
      this.notificationPermission = Notification.permission === "granted";
    }
  }

  _showNotification(title, body) {
    if (!this.notificationPermission || document.visibilityState === "visible")
      return;

    try {
      const notification = new Notification(title, {
        body: body,
        icon: `${this.baseUrl}/assets/upload/profile.png`,
        tag: "freelancehub-chat",
        renotify: true,
      });
      notification.onclick = () => {
        window.focus();
        notification.close();
      };
      setTimeout(() => notification.close(), 5000);
    } catch (e) {}
  }

  /* ── Online Status ─────────────────────────────────────────── */
  _startOnlineCheck() {
    this.onlineCheckInterval = setInterval(() => {
      this._checkOnlineStatuses();
    }, 30000);
  }

  _checkOnlineStatuses() {
    this.conversations.forEach((conv) => {
      if (!conv.otherId) return;
      fetch(
        `${this.baseUrl}/shared/online_status.php?user_id=${conv.otherId}`,
        {
          headers: { "X-Requested-With": "XMLHttpRequest" },
        },
      )
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            conv.isOnline = data.is_online;
            this._updateOnlineIndicator(conv.roomId, data.is_online);
          }
        })
        .catch(() => {});
    });
  }

  _updateOnlineIndicator(roomId, isOnline) {
    const roomEl = document.querySelector(
      `.room-item[data-room-id="${roomId}"]`,
    );
    if (roomEl) {
      const indicator = roomEl.querySelector(".online-indicator");
      if (indicator) indicator.classList.toggle("hidden", !isOnline);
    }

    if (roomId == this.selectedRoomId && this.els.chatHeaderStatus) {
      if (isOnline) {
        this.els.chatHeaderStatus.textContent = "Online";
        this.els.chatHeaderStatus.className =
          "text-[11px] text-green-500 font-medium";
      } else {
        this.els.chatHeaderStatus.textContent = "Offline";
        this.els.chatHeaderStatus.className =
          "text-[11px] text-gray-400 font-medium";
      }
    }
  }

  /* ── Load Conversations ────────────────────────────────────── */
  loadConversations() {
    fetch(`${this.baseUrl}/${this.role}/get_conversations.php`, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((r) => r.json())
      .then((data) => {
        const conversations = data.conversations || [];
        this.conversations = conversations.map((c) =>
          this._normalizeConversation(c),
        );
        this.allConversations = [...this.conversations];
        this.renderConversationList(this.conversations);
        this._updateTotalUnread();
        this._autoOpenLatestConversation();
      })
      .catch(() => {});
  }

  _normalizeConversation(c) {
    // Handle both shared endpoint fields (other_name) and role-specific fields
    const otherName = c.other_name || c.freelancer_name || c.client_name || "User";
    const otherImage = c.other_image || c.freelancer_image || c.client_image || null;
    const otherId = c.other_id || c.freelancer_id || c.client_id || null;

    return {
      roomId: c.room_id,
      contractId: c.contract_id,
      otherName: otherName,
      otherImage: otherImage,
      otherId: otherId,
      jobTitle: c.job_title || "",
      lastMessage: c.last_message,
      lastMessageTime: c.last_message_time,
      unreadCount: parseInt(c.unread_count) || 0,
      contractStatus: c.contract_status,
      budget: c.total_budget,
      isOnline: c.is_online || false,
    };
  }

  /* ── Auto-Open Latest Conversation ─────────────────────────── */
  _autoOpenLatestConversation() {
    if (this.selectedRoomId) return;
    if (this.conversations.length === 0) return;

    // Find the conversation with messages (most recent)
    const withMessages = this.conversations.filter(
      (c) => c.roomId && c.lastMessageTime,
    );
    const target =
      withMessages.length > 0
        ? withMessages[0]
        : this.conversations.find((c) => c.roomId);

    if (target && target.roomId) {
      this.selectRoom(target.roomId);
    }
  }

  /* ── Render Conversation List ──────────────────────────────── */
  renderConversationList(conversations) {
    if (!this.els.roomList) return;
    this.els.roomList.innerHTML = "";

    if (conversations.length === 0) {
      this.els.roomList.innerHTML = `
                <div class="text-center py-12 px-4">
                    <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-comment-dots text-xl text-gray-300"></i>
                    </div>
                    <p class="text-gray-500 text-sm font-medium">No conversations</p>
                    <p class="text-gray-400 text-xs mt-1">Messages from ${this.role === "client" ? "freelancers" : "clients"} will appear here</p>
                </div>`;
      return;
    }

    conversations.forEach((conv) => {
      this.els.roomList.appendChild(this._renderConversationItem(conv));
    });
  }

  _renderConversationItem(conv) {
    const div = document.createElement("div");
    div.className = `room-item flex items-center gap-3 px-4 py-3 border-b border-gray-50 cursor-pointer transition-all duration-150 ${conv.roomId == this.selectedRoomId ? "active" : ""}`;
    div.dataset.roomId = conv.roomId || "";
    div.dataset.otherName = conv.otherName;

    if (conv.roomId) {
      div.addEventListener("click", (e) => {
        e.preventDefault();
        this.selectRoom(conv.roomId);
      });
    }

    const avatarUrl = conv.otherImage
      ? this.baseUrl + "/" + this.escapeHtml(conv.otherImage)
      : "https://ui-avatars.com/api/?name=" +
        encodeURIComponent(conv.otherName) +
        "&background=6366f1&color=fff&bold=true&size=48";

    const preview = conv.lastMessage
      ? this.escapeHtml(conv.lastMessage)
      : "No messages yet";
    const timeStr = conv.lastMessageTime
      ? this._formatRelativeTime(conv.lastMessageTime)
      : "";

    div.innerHTML = `
            <div class="relative flex-shrink-0">
                <img src="${avatarUrl}" class="w-11 h-11 rounded-full object-cover border-2 border-gray-100" alt="">
                <span class="online-indicator absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-white ${conv.isOnline ? "" : "hidden"}"></span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-900 truncate">${this.escapeHtml(conv.otherName)}</p>
                    <span class="text-[10px] text-gray-400 flex-shrink-0">${timeStr}</span>
                </div>
                <p class="text-[11px] text-primary font-medium truncate mt-0.5">${this.escapeHtml(conv.jobTitle)}</p>
                <p class="text-xs text-gray-500 truncate mt-0.5">${preview}</p>
            </div>
            ${conv.unreadCount > 0 ? `<span class="unread-badge w-5 h-5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">${conv.unreadCount > 99 ? "99+" : conv.unreadCount}</span>` : ""}`;
    return div;
  }

  /* ── Select Room ───────────────────────────────────────────── */
  selectRoom(roomId) {
    if (!roomId) return;
    if (this.selectedRoomId === roomId) return;

    this.stopSSE();
    this.selectedRoomId = roomId;
    this.lastMessageId = 0;
    this._messageOffset = 0;

    // Show chat UI, hide placeholders
    if (this.els.placeholder) this.els.placeholder.classList.add("hidden");
    if (this.els.emptyChatMsg) this.els.emptyChatMsg.classList.add("hidden");
    if (this.els.chatInputArea)
      this.els.chatInputArea.classList.remove("hidden");
    if (this.els.chatHeader) {
      this.els.chatHeader.classList.remove("hidden");
      this.els.chatHeader.classList.add("flex");
    }

    // Update header info
    const conv = this.conversations.find((c) => c.roomId == roomId);
    if (conv) {
      if (this.els.chatHeaderName) {
        this.els.chatHeaderName.textContent = conv.otherName;
      }
      if (this.els.chatHeaderAvatar) {
        const avatarUrl = conv.otherImage
          ? this.baseUrl + "/" + conv.otherImage
          : "https://ui-avatars.com/api/?name=" +
            encodeURIComponent(conv.otherName) +
            "&background=6366f1&color=fff&bold=true&size=40";
        this.els.chatHeaderAvatar.src = avatarUrl;
      }
      this._updateOnlineIndicator(roomId, conv.isOnline);
    }

    // Highlight active room, clear its unread badge
    document.querySelectorAll(".room-item").forEach((el) => {
      const isActive = parseInt(el.dataset.roomId) === roomId;
      el.classList.toggle("active", isActive);
      if (isActive) {
        const badge = el.querySelector(".unread-badge");
        if (badge) badge.remove();
      }
    });
    if (conv) conv.unreadCount = 0;

    this._showChatOnMobile();
    this.loadMessages(roomId);
    this.startSSE(roomId);
    this._markAsRead(roomId);
    this._updateTotalUnread();

    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set("room", roomId);
    window.history.pushState({}, "", url);
  }

  _showChatOnMobile() {
    if (this.els.roomsPanel) {
      this.els.roomsPanel.classList.add("hidden");
      this.els.roomsPanel.classList.add("sm:flex");
    }
    if (this.els.chatArea) {
      this.els.chatArea.classList.remove("hidden");
      this.els.chatArea.classList.add("flex");
    }
  }

  _showRoomsOnMobile() {
    if (this.els.chatArea) {
      this.els.chatArea.classList.add("hidden");
      this.els.chatArea.classList.remove("flex");
    }
    if (this.els.roomsPanel) {
      this.els.roomsPanel.classList.remove("hidden");
      this.els.roomsPanel.classList.add("flex");
    }
  }

  /* ── Load Messages ─────────────────────────────────────────── */
  loadMessages(roomId) {
    if (this.els.chatMessages) this.els.chatMessages.innerHTML = "";
    this._messageOffset = 0;

    const endpoint = `${this.baseUrl}/${this.role}/load_messages.php?room_id=${roomId}`;

    fetch(endpoint, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((r) => r.json())
      .then((data) => {
        const messages = data.messages || [];

        if (messages.length === 0) {
          if (this.els.emptyChatMsg)
            this.els.emptyChatMsg.classList.remove("hidden");
          if (this.els.chatMessages)
            this.els.chatMessages.classList.add("hidden");
        } else {
          if (this.els.emptyChatMsg)
            this.els.emptyChatMsg.classList.add("hidden");
          if (this.els.chatMessages)
            this.els.chatMessages.classList.remove("hidden");
          this.renderMessages(messages);
          this.lastMessageId = messages[messages.length - 1].id;
          this._messageOffset = messages.length;
        }
        this.scrollToBottom(true);
      })
      .catch((err) => {
        console.error("Failed to load messages:", err);
      });
  }

  /* ── Render Messages ───────────────────────────────────────── */
  renderMessages(messages) {
    if (!this.els.chatMessages) return;
    let lastDate = null;

    messages.forEach((msg) => {
      const msgDate = this._getDateKey(msg.created_at);
      if (msgDate !== lastDate) {
        this.els.chatMessages.appendChild(
          this._createDateSeparator(msg.created_at),
        );
        lastDate = msgDate;
      }
      this.els.chatMessages.appendChild(this._createMessageBubble(msg));
    });
  }

  appendMessage(message) {
    if (!this.els.chatMessages) return;
    if (this.els.emptyChatMsg) this.els.emptyChatMsg.classList.add("hidden");
    if (this.els.chatMessages) this.els.chatMessages.classList.remove("hidden");

    // Prevent duplicates
    const existingIds = Array.from(
      this.els.chatMessages.querySelectorAll("[data-message-id]"),
    ).map((el) => parseInt(el.dataset.messageId));
    if (existingIds.includes(message.id)) return;

    const msgDate = this._getDateKey(message.created_at);
    const lastChild = this.els.chatMessages.lastElementChild;
    if (lastChild) {
      const lastDate = lastChild.dataset ? lastChild.dataset.dateKey : null;
      if (lastDate !== msgDate) {
        this.els.chatMessages.appendChild(
          this._createDateSeparator(message.created_at),
        );
      }
    }

    this.els.chatMessages.appendChild(this._createMessageBubble(message));
    this.lastMessageId = message.id;

    // Auto-scroll if user is near bottom or sent by self
    if (this._scrollLocked || parseInt(message.sender_id) === this.userId) {
      this.scrollToBottom(true);
    }

    // Browser notification for incoming messages
    if (parseInt(message.sender_id) !== this.userId) {
      this._showNotification(
        message.sender_name || "New Message",
        message.message_text || "Sent an attachment",
      );
    }
  }

  _createMessageBubble(msg) {
    const isMine = parseInt(msg.sender_id) === this.userId;
    const div = document.createElement("div");
    div.className = `flex ${isMine ? "justify-end" : "justify-start"}`;
    div.dataset.messageId = msg.id;

    const time = this.formatTime(msg.created_at);

    const avatarUrl = msg.sender_image
      ? this.baseUrl + "/" + this.escapeHtml(msg.sender_image)
      : "https://ui-avatars.com/api/?name=" +
        encodeURIComponent(msg.sender_name || "") +
        "&background=6366f1&color=fff&bold=true&size=32";

    let avatarHtml = "";
    if (!isMine) {
      avatarHtml = `<img src="${avatarUrl}" class="w-7 h-7 rounded-full object-cover flex-shrink-0" alt="">`;
    }

    const readCheck = isMine
      ? msg.is_read
        ? '<span class="text-blue-300 text-[10px] ml-1"><i class="fas fa-check-double"></i></span>'
        : '<span class="text-blue-300/60 text-[10px] ml-1"><i class="fas fa-check"></i></span>'
      : "";

    let contentHtml = "";
    if (msg.payload && msg.payload.path) {
      const fileIcon = this._getFileIcon(msg.payload.type);
      const fileSize = msg.payload.size
        ? (msg.payload.size / 1024).toFixed(1) + " KB"
        : "";
      contentHtml = `
                <a href="${this.escapeHtml(msg.payload.path)}" target="_blank"
                   class="flex items-center gap-2 ${isMine ? "text-white/90" : "text-gray-700"} hover:underline">
                    <i class="fas ${fileIcon} text-lg"></i>
                    <div class="min-w-0">
                        <p class="text-sm truncate max-w-[200px]">${this.escapeHtml(msg.payload.name || "Attachment")}</p>
                        ${fileSize ? `<p class="text-[10px] opacity-70">${fileSize}</p>` : ""}
                    </div>
                </a>`;
    }

    if (msg.message_text) {
      contentHtml += `<p class="text-sm leading-relaxed whitespace-pre-line">${this.escapeHtml(msg.message_text)}</p>`;
    }

    if (!contentHtml) {
      contentHtml = '<p class="text-sm text-gray-400 italic">Empty message</p>';
    }

    div.innerHTML = `
            <div class="flex items-end gap-2 max-w-[75%] ${isMine ? "flex-row-reverse" : ""}">
                ${avatarHtml}
                <div>
                    <div class="${
                      isMine
                        ? "bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-2xl rounded-br-md"
                        : "bg-gray-100 text-gray-900 rounded-2xl rounded-bl-md"
                    } px-4 py-2.5 shadow-sm">
                        ${contentHtml}
                    </div>
                    <div class="flex items-center gap-1 mt-1 ${isMine ? "justify-end" : "justify-start"}">
                        <p class="text-[10px] text-gray-400">${time}</p>
                        ${readCheck}
                    </div>
                </div>
            </div>`;
    return div;
  }

  _getFileIcon(mimeType) {
    if (!mimeType) return "fa-file";
    if (mimeType.includes("pdf")) return "fa-file-pdf text-red-400";
    if (mimeType.includes("word") || mimeType.includes("doc"))
      return "fa-file-word text-blue-400";
    if (mimeType.includes("zip")) return "fa-file-zipper text-yellow-400";
    if (mimeType.includes("image")) return "fa-file-image text-green-400";
    return "fa-file text-gray-400";
  }

  _createDateSeparator(dateStr) {
    const div = document.createElement("div");
    div.className = "flex items-center gap-3 my-4";
    div.dataset.dateKey = this._getDateKey(dateStr);
    const label = this.formatDateSeparator(dateStr);
    div.innerHTML = `
            <div class="flex-1 h-px bg-gray-200"></div>
            <span class="text-[11px] font-medium text-gray-400 bg-gray-50 px-3 py-1 rounded-full">${label}</span>
            <div class="flex-1 h-px bg-gray-200"></div>`;
    return div;
  }

  /* ── Send Message ──────────────────────────────────────────── */
  async sendMessage() {
    if (!this.els.chatInput || !this.selectedRoomId) return;
    const text = this.els.chatInput.value.trim();

    if (!text && !this._pendingFile) return;
    if (this.sending) return;

    this.sending = true;
    if (this.els.chatSendBtn) this.els.chatSendBtn.disabled = true;

    let payload = null;

    if (this._pendingFile) {
      payload = await this._uploadFile(this.selectedRoomId);
      if (!payload) {
        this.sending = false;
        if (this.els.chatSendBtn) this.els.chatSendBtn.disabled = false;
        return;
      }
    }

    const messageText = text || "";

    this.els.chatInput.value = "";
    this._autoResizeInput();

    const body = new URLSearchParams();
    body.append("csrf_token", this.csrfToken);
    body.append("room_id", this.selectedRoomId);
    body.append("message_text", messageText);

    fetch(`${this.baseUrl}/${this.role}/send_message.php`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.message) {
          if (payload) {
            data.message.payload = payload;
          }
          this.appendMessage(data.message);
          this._scrollLocked = true;
          this.scrollToBottom(true);
          this._updateConversationListAfterSend(data.message);
        }
      })
      .catch(() => {
        if (this.els.chatInput) this.els.chatInput.value = text;
      })
      .finally(() => {
        this.sending = false;
        if (this.els.chatSendBtn) this.els.chatSendBtn.disabled = false;
      });
  }

  /* ── Update Conversation List After Send ───────────────────── */
  _updateConversationListAfterSend(message) {
    const conv = this.conversations.find(
      (c) => c.roomId == this.selectedRoomId,
    );
    if (conv) {
      conv.lastMessage = message.message_text;
      conv.lastMessageTime = message.created_at;
    }

    // Re-sort conversations: most recently active first
    this.conversations.sort((a, b) => {
      if (!a.lastMessageTime) return 1;
      if (!b.lastMessageTime) return -1;
      return new Date(b.lastMessageTime) - new Date(a.lastMessageTime);
    });
    this.allConversations = [...this.conversations];

    this.renderConversationList(this.conversations);

    // Re-highlight active room
    document.querySelectorAll(".room-item").forEach((el) => {
      if (parseInt(el.dataset.roomId) == this.selectedRoomId) {
        el.classList.add("active");
      }
    });
  }

  /* ── Mark as Read ──────────────────────────────────────────── */
  _markAsRead(roomId) {
    const body = new URLSearchParams();
    body.append("csrf_token", this.csrfToken);
    body.append("room_id", roomId);

    fetch(`${this.baseUrl}/${this.role}/mark_read.php`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then((r) => r.json())
      .then(() => {
        const conv = this.conversations.find((c) => c.roomId == roomId);
        if (conv) conv.unreadCount = 0;
        this._updateTotalUnread();
      })
      .catch(() => {});
  }

  /* ── SSE Streaming ─────────────────────────────────────────── */
  startSSE(roomId) {
    this.stopSSE();

    const streamUrl = `${this.baseUrl}/${this.role}/chat_stream.php?room_id=${roomId}&last_id=${this.lastMessageId}`;

    this.eventSource = new EventSource(streamUrl);

    this.eventSource.addEventListener("message", (e) => {
      try {
        const msg = JSON.parse(e.data);
        if (parseInt(msg.sender_id) !== this.userId) {
          this.appendMessage(msg);
          this._markAsRead(roomId);
        }
        if (msg.id) this.lastMessageId = msg.id;
      } catch (err) {}
    });

    this.eventSource.addEventListener("typing", (e) => {
      try {
        const data = JSON.parse(e.data);
        if (data.is_typing) {
          this.showTypingIndicator(data.user_name || "Someone");
        } else {
          this.hideTypingIndicator();
        }
      } catch (err) {}
    });

    this.eventSource.addEventListener("connected", () => {});
    this.eventSource.addEventListener("heartbeat", () => {});

    this.eventSource.onerror = () => {
      setTimeout(() => {
        if (this.selectedRoomId === roomId) {
          this.startSSE(roomId);
        }
      }, 3000);
    };
  }

  stopSSE() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
  }

  /* ── Search Conversations ──────────────────────────────────── */
  searchConversations(query) {
    const q = (query || "").toLowerCase().trim();
    if (!q) {
      this.conversations = [...this.allConversations];
    } else {
      this.conversations = this.allConversations.filter(
        (c) =>
          c.otherName.toLowerCase().includes(q) ||
          c.jobTitle.toLowerCase().includes(q) ||
          (c.lastMessage && c.lastMessage.toLowerCase().includes(q)),
      );
    }
    this.renderConversationList(this.conversations);
  }

  /* ── Unread Badge ──────────────────────────────────────────── */
  updateUnreadBadge(roomId, count) {
    const roomEl = document.querySelector(
      `.room-item[data-room-id="${roomId}"]`,
    );
    if (!roomEl) return;

    const existing = roomEl.querySelector(".unread-badge");
    if (existing) existing.remove();

    if (count > 0) {
      const badge = document.createElement("span");
      badge.className =
        "unread-badge w-5 h-5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0";
      badge.textContent = count > 99 ? "99+" : count;
      roomEl.appendChild(badge);
    }
  }

  _updateTotalUnread() {
    const total = this.conversations.reduce(
      (sum, c) => sum + (c.unreadCount || 0),
      0,
    );
    if (!this.els.unreadBadge) return;
    if (total > 0) {
      this.els.unreadBadge.textContent = total > 99 ? "99+" : total;
      this.els.unreadBadge.classList.remove("hidden");
    } else {
      this.els.unreadBadge.classList.add("hidden");
    }
  }

  /* ── Scroll ────────────────────────────────────────────────── */
  scrollToBottom(instant = false) {
    if (this.els.chatMessages) {
      requestAnimationFrame(() => {
        this.els.chatMessages.scrollTo({
          top: this.els.chatMessages.scrollHeight,
          behavior: instant ? "instant" : "smooth",
        });
      });
    }
  }

  /* ── Date Formatting ───────────────────────────────────────── */
  formatDateSeparator(dateStr) {
    const d = this._parseDate(dateStr);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);

    if (d.toDateString() === today.toDateString()) return "Today";
    if (d.toDateString() === yesterday.toDateString()) return "Yesterday";
    return d.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  }

  _getDateKey(dateStr) {
    const d = this._parseDate(dateStr);
    return d.toDateString();
  }

  formatTime(timestamp) {
    if (!timestamp) return "";
    const d = this._parseDate(timestamp);
    const hours = d.getHours().toString().padStart(2, "0");
    const mins = d.getMinutes().toString().padStart(2, "0");
    return `${hours}:${mins}`;
  }

  _formatRelativeTime(timestamp) {
    if (!timestamp) return "";
    const d = this._parseDate(timestamp);
    const now = new Date();
    const diffMs = now - d;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHr = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHr / 24);

    if (diffSec < 60) return "now";
    if (diffMin < 60) return `${diffMin}m`;
    if (diffHr < 24) return `${diffHr}h`;
    if (diffDay === 1) return "Yesterday";
    if (diffDay < 7) return `${diffDay}d`;
    return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  }

  _parseDate(dateStr) {
    if (!dateStr) return new Date();
    // Handle MySQL datetime format
    const str = dateStr.replace(" ", "T");
    if (!str.includes("Z") && !str.includes("+")) {
      return new Date(str + "Z");
    }
    return new Date(str);
  }

  /* ── XSS Prevention ───────────────────────────────────────── */
  escapeHtml(text) {
    if (!text) return "";
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(text).replace(/[&<>"']/g, (c) => map[c]);
  }
}
