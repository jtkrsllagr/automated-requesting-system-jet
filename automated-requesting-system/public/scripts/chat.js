/**
 * chat.js — ARS Messaging
 */
(function () {
    'use strict';

    var BASE = window.ARS_BASE || '';

    var csrf = function () {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    };

    // ── DOM refs ── //
    var searchInput = document.getElementById('chatSearch');
    var userList = document.getElementById('chatUserList');
    var welcome = document.getElementById('chatWelcome');
    var conversation = document.getElementById('chatConversation');
    var messagesEl = document.getElementById('chatMessages');
    var noMessages = document.getElementById('chatNoMessages');
    var convName = document.getElementById('chatConvName');
    var convStatus = document.getElementById('chatConvStatus');
    var convAvatar = document.getElementById('chatConvAvatar');
    var inputEl = document.getElementById('chatInput');
    var sendBtn = document.getElementById('chatSendBtn');
    var unblockBtn = document.getElementById('chatUnblockBtn');
    var blockedNotice = document.getElementById('chatBlockedNotice');
    var inputBar = document.getElementById('chatInputBar');
    var backBtn = document.getElementById('chatBackBtn');
    var chatLayout = document.getElementById('chatLayout');
    var loadMoreWrap = document.getElementById('chatLoadMore');
    var loadMoreBtn = document.getElementById('chatLoadMoreBtn');
    var typingRow = document.getElementById('chatTypingRow');
    var typingLabel = document.getElementById('chatTypingLabel');
    var charCount = document.getElementById('chatCharCount');
    var filterRow = document.getElementById('chatFilterRow');
    var menuBtn = document.getElementById('chatMenuBtn');
    var dropdown = document.getElementById('chatDropdown');
    var blockBtn = document.getElementById('chatBlockBtn');
    var muteBtn = document.getElementById('chatMuteBtn');
    var viewProfileBtn = document.getElementById('chatViewProfileBtn');
    var unreadBanner = document.getElementById('chatUnreadBanner');
    var unreadBannerText = document.getElementById('chatUnreadBannerText');
    var welcomeCta = document.getElementById('chatWelcomeCta');
    var swipeHint = document.getElementById('chatSwipeHint');
    var blockModal = document.getElementById('chatBlockModal');
    var blockModalName = document.getElementById('chatBlockModalName');
    var blockCancel = document.getElementById('chatBlockCancel');
    var blockConfirm = document.getElementById('chatBlockConfirm');
    var profileModal = document.getElementById('chatProfileModal');
    var profileClose = document.getElementById('chatProfileClose');
    var profileAvatar = document.getElementById('chatProfileAvatar');
    var profileName = document.getElementById('chatProfileName');
    var profileDept = document.getElementById('chatProfileDept');
    var profileStatus = document.getElementById('chatProfileStatus');

    var attachBtn = document.getElementById('chatAttachBtn');
    var fileInput = document.getElementById('chatFileInput');
    var stickerBtn = document.getElementById('chatStickerBtn');
    var stickerPicker = document.getElementById('chatStickerPicker');


    // ── State ── //
    var activeUserId = null;
    var activeUserName = '';
    var activeUserAvatar = '';
    var activeUserDept = '';
    var lastMessageId = 0;
    var oldestMsgId = null;
    var allMsgLoaded = false;
    var pollTimer = null;
    var unreadTimer = null;
    var typingTimer = null;
    var typingPollTimer = null;
    var allUsers = [];
    var isBlocked = false;
    var isMuted = false;
    var mutedUsers = {};
    var activeFilter = 'all';

    var PAGE_SIZE = 30;
    var EMOJIS = ['\u{1F44D}', '\u2764\uFE0F', '\u{1F602}', '\u2705', '\u{1F64F}'];

    // ── Utility ── //
    function esc(s) {
        var AMP = String.fromCharCode(38); // &
        var LT = String.fromCharCode(60); // <
        var GT = String.fromCharCode(62); // >
        var QUOT = String.fromCharCode(34); // "
        return String(s)
            .replace(/&/g, AMP + 'amp;')
            .replace(/</g, AMP + 'lt;')
            .replace(/>/g, AMP + 'gt;')
            .replace(/"/g, AMP + 'quot;');
    }

    // Server sends naive "Y-m-d H:i:s" strings that are actually UTC (MySQL NOW()
    // / gmdate() are both UTC). JS treats a string with no timezone marker as
    // LOCAL time, so we must mark it as UTC ourselves before parsing, or every
    // timestamp renders shifted by the viewer's UTC offset.
    function utcDate(iso) {
        var s = iso.replace(' ', 'T');
        if (!/Z$|[+-]\d\d:?\d\d$/.test(s)) s += 'Z';
        return new Date(s);
    }

    function timeStr(iso) {
        var d = utcDate(iso), h = d.getHours(), m = d.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + String(m).padStart(2, '0') + ' ' + ampm;
    }

    function relativeTime(iso) {
        var diff = (Date.now() - utcDate(iso).getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function friendlyDate(iso) {
        var d = utcDate(iso), today = new Date(), yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        if (d.toDateString() === today.toDateString()) return 'Today';
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function isOnline(lastSeenAt) {
        if (!lastSeenAt) return false;
        return (Date.now() - utcDate(lastSeenAt).getTime()) < 5 * 60 * 1000;
    }

    function avatarInitials(name) {
        var parts = (name || '').trim().split(' ').filter(Boolean);
        if (!parts.length) return '?';
        return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
    }

    function post(url, data) {
        var fd = new FormData();
        for (var k in data) fd.append(k, data[k]);
        fd.append('csrf_token', csrf());
        return fetch(BASE + url, { method: 'POST', body: fd, credentials: 'same-origin' });
    }

    function get(url) {
        return fetch(BASE + url, { credentials: 'same-origin' });
    }

    // ── Filter tabs ── //
    filterRow.addEventListener('click', function (e) {
        var pill = e.target.closest('.chat-filter-pill');
        if (!pill) return;
        filterRow.querySelectorAll('.chat-filter-pill').forEach(function (p) {
            p.classList.remove('chat-filter-pill--active');
        });
        pill.classList.add('chat-filter-pill--active');
        activeFilter = pill.dataset.filter;
        renderUserList(allUsers);
    });

    if (welcomeCta) {
        welcomeCta.addEventListener('click', function () {
            // On mobile: chat-main covers the sidebar. Remove the open class
            // so the sidebar slides back into view (same as pressing Back).
            if (window.innerWidth <= 700) {
                chatLayout.classList.remove('chat-layout--open');
            }
            // If contacts never loaded, retry now.
            if (allUsers.length === 0) {
                loadUsers();
            }
            // Always focus + highlight the search input so the user can type immediately.
            searchInput.focus();
            searchInput.select();
        });
    }

    // ── User list ── //
    function loadUsers() {
        get('/chat/users')
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (users) {
                allUsers = Array.isArray(users) ? users : [];
                renderUserList(allUsers);
                var total = allUsers.reduce(function (s, u) { return s + (u.unread_count || 0); }, 0);
                if (total > 0 && !activeUserId) {
                    unreadBannerText.textContent = 'You have ' + total + ' unread message' + (total > 1 ? 's' : '');
                    unreadBanner.classList.remove('dept-hidden');
                } else {
                    unreadBanner.classList.add('dept-hidden');
                }
            })
            .catch(function (err) {
                console.error('[chat] loadUsers failed:', err);
                userList.innerHTML = '<div class="chat-empty-state">'
                    + '<i class="ti ti-wifi-off"></i>'
                    + '<span>Could not load contacts.</span>'
                    + '<button class="chat-retry-btn" id="chatRetryBtn">Retry</button>'
                    + '</div>';
                var retryBtn = document.getElementById('chatRetryBtn');
                if (retryBtn) retryBtn.addEventListener('click', loadUsers);
            });
    }

    function renderUserList(users) {
        var q = searchInput.value.trim().toLowerCase();
        var filtered = q
            ? users.filter(function (u) {
                return u.full_name.toLowerCase().includes(q) || (u.department || '').toLowerCase().includes(q);
              })
            : users;

        if (activeFilter === 'unread') filtered = filtered.filter(function (u) { return u.unread_count > 0; });
        else if (activeFilter === 'online') filtered = filtered.filter(function (u) { return isOnline(u.last_seen_at); });
        else if (activeFilter === 'all' && !q) {
            filtered = filtered.filter(function (u) { return !!u.last_message_at; });
        }

        if (!filtered.length) {
            var msg = activeFilter === 'unread' ? 'No unread messages.'
                    : activeFilter === 'online' ? 'No colleagues online.'
                    : activeFilter === 'all' && !q ? 'No messages yet' : 'No users found.';
            userList.innerHTML = '<div class="chat-empty-state">' + msg + '</div>';
            return;
        }

        userList.innerHTML = filtered.map(function (u) {
            var initials = avatarInitials(u.full_name);
            var online = isOnline(u.last_seen_at);
            var badge = u.unread_count > 0 ? '<span class="chat-unread-badge">' + u.unread_count + '</span>' : '';
            var statusDot = '<span class="chat-status-dot' + (online ? ' online' : '') + '"></span>';
            var lastMsg = u.last_message
                ? '<span class="chat-last-msg">' + esc(u.last_message.slice(0, 45)) + (u.last_message.length > 45 ? '\u2026' : '') + '</span>'
                : '';
            var timeAgo = u.last_message_at ? '<span class="chat-last-time">' + relativeTime(u.last_message_at) + '</span>' : '';
            var avatarHtml = u.avatar && !u.avatar.includes('default-avatar')
                ? '<img src="' + esc(u.avatar) + '" alt="" class="chat-ulist-img">'
                : '<div class="chat-ulist-initials">' + esc(initials) + '</div>';
            var activeClass = u.id === activeUserId ? ' chat-user-item--active' : '';

            return '<div class="chat-user-item' + activeClass + '" data-id="' + u.id + '"'
                + ' data-name="' + esc(u.full_name) + '" data-dept="' + esc(u.department || '') + '"'
                + ' data-avatar="' + esc(u.avatar || '') + '" data-seen="' + esc(u.last_seen_at || '') + '">'
                + '<div class="chat-ulist-avatar">' + avatarHtml + badge + statusDot + '</div>'
                + '<div class="chat-ulist-body">'
                +   '<div class="chat-ulist-name">' + esc(u.full_name) + '</div>'
                +   (lastMsg || timeAgo ? '<div class="chat-ulist-meta">' + lastMsg + timeAgo + '</div>' : '')
                + '</div></div>';
        }).join('');

        userList.querySelectorAll('.chat-user-item').forEach(function (el) {
            el.addEventListener('click', function () {
                openConversation(parseInt(el.dataset.id), el.dataset.name, el.dataset.dept, el.dataset.avatar, el.dataset.seen);
            });
        });
    }

    // ── Conversation ── //
    function openConversation(userId, name, dept, avatar, lastSeen) {
        activeUserId = userId;
        activeUserName = name;
        activeUserAvatar = avatar;
        activeUserDept = dept;
        lastMessageId = 0;
        oldestMsgId = null;
        allMsgLoaded = false;
        isMuted = !!mutedUsers[userId];

        convName.textContent = name;
        var online = isOnline(lastSeen);
        convStatus.innerHTML = online
            ? '<span class="chat-online-badge">\u25CF Online</span>'
            : (dept ? esc(dept) : '');

        if (avatar && !avatar.includes('default-avatar')) {
            convAvatar.innerHTML = '<img src="' + esc(avatar) + '" alt="" class="chat-conv-img">';
        } else {
            convAvatar.innerHTML = '<div class="chat-conv-initials">' + esc(avatarInitials(name)) + '</div>';
        }

        updateMuteLabel();
        welcome.classList.add('dept-hidden');
        unreadBanner.classList.add('dept-hidden');
        conversation.classList.remove('dept-hidden');
        chatLayout.classList.add('chat-layout--open');

        if (window.innerWidth <= 700 && !sessionStorage.getItem('ars_swipe_shown')) {
            swipeHint.classList.remove('dept-hidden');
            sessionStorage.setItem('ars_swipe_shown', '1');
            setTimeout(function () { swipeHint.classList.add('dept-hidden'); }, 5000);
        }

        userList.querySelectorAll('.chat-user-item').forEach(function (el) {
            el.classList.toggle('chat-user-item--active', parseInt(el.dataset.id) === userId);
        });

        messagesEl.innerHTML = '';
        messagesEl.appendChild(loadMoreWrap);
        messagesEl.appendChild(noMessages);
        noMessages.classList.add('dept-hidden');
        loadMoreWrap.classList.add('dept-hidden');
        closeDropdown();
        stopTypingPoll();

        get('/chat/block-status?with=' + userId)
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                updateBlockState(data.blocked === true);
            })
            .catch(function (err) {
                console.error('[chat] block-status fetch failed:', err);
                updateBlockState(false);
            });

        loadMessages(userId, true);
        post('/chat/mark-read', { with: userId }).catch(function (err) { console.error("[chat]", err); });

        clearInterval(pollTimer);
        pollTimer = setInterval(function () { pollMessages(userId); }, 2000);
        startTypingPoll(userId);
    }

    // ── Messages ── //
    function loadMessages(userId, scrollToBottom, before) {
        var url = '/chat/messages?with=' + userId + '&limit=' + PAGE_SIZE;
        if (before) url += '&before=' + before;

        get(url)
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                var msgs    = data.messages !== undefined ? data.messages : data;
                var hasMore = data.has_more !== undefined ? data.has_more : (msgs.length === PAGE_SIZE);

                if (msgs.length === 0 && !before) {
                    noMessages.classList.remove('dept-hidden');
                } else {
                    noMessages.classList.add('dept-hidden');
                }

                if (before) {
                    var anchor = messagesEl.querySelector('.chat-msg');
                    var prev = messagesEl.scrollHeight;
                    prependMessages(msgs, anchor);
                    messagesEl.scrollTop += messagesEl.scrollHeight - prev;
                } else {
                    renderMessages(msgs);
                    if (msgs.length) {
                        lastMessageId = parseInt(msgs[msgs.length - 1].id);
                        oldestMsgId = parseInt(msgs[0].id);
                    }
                    if (scrollToBottom) scrollBottom();
                }

                if (msgs.length && before) oldestMsgId = parseInt(msgs[0].id);

                allMsgLoaded = !hasMore;
                loadMoreWrap.classList.toggle('dept-hidden', allMsgLoaded);
            })
            .catch(function (err) { console.error("[chat]", err); });
    }

    function pollMessages(userId) {
        if (activeUserId !== userId) return;
        get('/chat/poll?with=' + userId + '&after=' + lastMessageId)
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (msgs) {
                if (!msgs.length) return;

                // Filter out any message IDs already rendered in the DOM
                // (own messages appended optimistically on send)
                var newMsgs = msgs.filter(function (m) {
                    return !messagesEl.querySelector('[data-id="' + m.id + '"]');
                });

                if (newMsgs.length) {
                    appendMessages(newMsgs);
                    scrollBottom();
                }

                // Always advance lastMessageId to the highest seen id
                var maxId = parseInt(msgs[msgs.length - 1].id);
                if (maxId > lastMessageId) lastMessageId = maxId;

                // Update read receipts on already-rendered own messages
                msgs.forEach(function (m) {
                    if (!m.is_mine || !m.is_read) return;
                    var el = messagesEl.querySelector('[data-id="' + m.id + '"] .chat-msg-seen');
                    if (!el) {
                        var wrap = messagesEl.querySelector('[data-id="' + m.id + '"] .chat-msg-meta');
                        if (wrap) {
                            var seen = document.createElement('span');
                            seen.className = 'chat-msg-seen';
                            seen.textContent = '\u2713\u2713 Seen';
                            wrap.appendChild(seen);
                        }
                    }
                });

            })
            .catch(function (err) { console.error("[chat]", err); });
    }

    // ── Rendering ── //
    var _lastRenderedDate = null;

    function renderMessages(msgs) {
        _lastRenderedDate = null;
        Array.from(messagesEl.children).forEach(function (el) {
            if (el !== loadMoreWrap && el !== noMessages) el.remove();
        });
        if (!msgs.length) return;
        appendMessages(msgs);
    }

    function prependMessages(msgs, anchor) {
        if (!msgs.length) return;
        var frag = document.createDocumentFragment();
        var lastDate = null;
        msgs.forEach(function (m) {
            var sep = maybeDateSep(m.sent_at, lastDate);
            if (sep) { frag.appendChild(sep); lastDate = utcDate(m.sent_at).toDateString(); }
            frag.appendChild(buildMessageEl(m));
        });
        messagesEl.insertBefore(frag, anchor);
    }

    function appendMessages(msgs) {
        msgs.forEach(function (m) {
            var sep = maybeDateSep(m.sent_at, _lastRenderedDate);
            if (sep) {
                messagesEl.appendChild(sep);
            }
            _lastRenderedDate = utcDate(m.sent_at).toDateString();
            messagesEl.appendChild(buildMessageEl(m));
        });
    }

    function maybeDateSep(iso, lastDate) {
        var d = utcDate(iso).toDateString();
        if (d === lastDate) return null;
        var el = document.createElement('div');
        el.className = 'chat-date-sep';
        el.textContent = friendlyDate(iso);
        return el;
    }

    function updateReactions(msgWrap, reactions) {
        var bar = msgWrap.querySelector('.chat-reactions-bar');
        if (!bar) return;
        bar.innerHTML = '';
        (reactions || []).forEach(function (r) {
            var chip = document.createElement('span');
            chip.className = 'chat-reaction-chip';
            chip.textContent = r.emoji + ' ' + r.count;
            bar.appendChild(chip);
        });
    }

    function buildMessageEl(m) {
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg ' + (m.is_mine ? 'chat-msg--mine' : 'chat-msg--theirs');
        wrap.dataset.id = m.id;

        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';

        if (m.message_type === 'sticker') {
            bubble.className += ' chat-bubble--sticker';
            bubble.textContent = m.message;
        } else if (m.message_type === 'form_share') {
            bubble.className += ' chat-bubble--form-share';
            var statusLabel = (m.shared_form_status || '').replace(/_/g, ' ');
            var card = document.createElement('a');
            card.className = 'chat-form-card';
            card.href = m.shared_form_url || (BASE + '/forms/view/' + m.form_id);
            card.innerHTML =
                '<div class="chat-form-card-header"><i class="ti ti-file-description"></i> '
                + esc(m.shared_form_label || 'Form') + ' #' + esc(m.form_id) + '</div>'
                + '<div class="chat-form-card-sub">' + esc(statusLabel || 'View request') + '</div>'
                + (m.message ? '<div class="chat-form-card-note">' + esc(m.message) + '</div>' : '');
            bubble.appendChild(card);
        } else if (m.message_type === 'attachment') {
            bubble.className += ' chat-bubble--attachment';
            var isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(m.attachment_url || '');
            if (isImage) {
                var img = document.createElement('img');
                img.src = m.attachment_url;
                img.className = 'chat-attachment-img';
                img.alt = m.message;
                bubble.appendChild(img);
            } else {
                bubble.innerHTML = '<i class="ti ti-file-download"></i> '
                    + '<a href="' + esc(m.attachment_url) + '" target="_blank" class="chat-attachment-link">'
                    + esc(m.message) + '</a>';
            }
        } else {
            bubble.textContent = m.message;
        }

        var meta = document.createElement('div');
        meta.className = 'chat-msg-meta';

        var timeNode = document.createElement('span');
        timeNode.className = 'chat-msg-time';
        timeNode.textContent = timeStr(m.sent_at);
        meta.appendChild(timeNode);

        if (m.is_mine && m.is_read) {
            var seen = document.createElement('span');
            seen.className = 'chat-msg-seen';
            seen.textContent = '\u2713\u2713 Seen';
            meta.appendChild(seen);
        }

        var actions = document.createElement('div');
        actions.className = 'chat-msg-actions';

        EMOJIS.forEach(function (emoji) {
            var btn = document.createElement('button');
            btn.className = 'chat-msg-action-btn chat-react-btn';
            btn.textContent = emoji;
            btn.title = 'React with ' + emoji;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                post('/chat/react', { message_id: m.id, emoji: emoji })
                    .then(function (r) { return r.json(); })
                    .then(function (res) { if (res.ok) updateReactions(wrap, res.reactions); })
                    .catch(function (err) { console.error('[chat]', err); });
            });
            actions.appendChild(btn);
        });

        if (m.message_type === 'text' && !m.is_mine) {
            var replyBtn = document.createElement('button');
            replyBtn.className = 'chat-msg-action-btn';
            replyBtn.innerHTML = '<i class="ti ti-corner-up-left"></i> Reply';
            replyBtn.addEventListener('click', function () {
                inputEl.value = '> ' + m.message.slice(0, 80) + (m.message.length > 80 ? '\u2026' : '') + '\n';
                inputEl.focus();
                charCount.textContent = inputEl.value.length + ' / 2000';
            });
            actions.appendChild(replyBtn);
        }

        if (m.is_mine) {
            var delBtn = document.createElement('button');
            delBtn.className = 'chat-msg-action-btn chat-delete-btn';
            delBtn.innerHTML = '<i class="ti ti-trash"></i> Delete';
            delBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (!confirm('Delete this message?')) return;
                post('/chat/delete', { message_id: m.id })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.ok) wrap.remove();
                        else showToast('Could not delete message.', 'error');
                    })
                    .catch(function () { showToast('Network error.', 'error'); });
            });
            actions.appendChild(delBtn);
        }

        wrap.appendChild(bubble);
        wrap.appendChild(meta);
        wrap.appendChild(actions);

        var reactionsBar = document.createElement('div');
        reactionsBar.className = 'chat-reactions-bar';
        wrap.appendChild(reactionsBar);
        if (m.reactions && m.reactions.length) updateReactions(wrap, m.reactions);

        return wrap;
    }

    function scrollBottom() { messagesEl.scrollTop = messagesEl.scrollHeight; }

    // ── Send ── //
    function sendMessage() {
        var text = inputEl.value.trim();
        if (!text || !activeUserId || isBlocked) return;
        inputEl.value = '';
        ArsStyle.setVars(inputEl, { height: 'auto' });
        charCount.textContent = '0 / 2000';

        post('/chat/send', { receiver_id: activeUserId, message: text })
            .then(function (r) {
                if (r.status === 403) {
                    updateBlockState(true);
                    inputEl.value = text;
                    return null;
                }
                return r.json();
            })
            .then(function (res) {
                if (!res) return;
                if (res.error) { showToast('Failed to send: ' + res.error, 'error'); inputEl.value = text; return; }
                var newId = parseInt(res.id);
                noMessages.classList.add('dept-hidden');
                appendMessages([{ id: newId, message: text, sent_at: res.sent_at, is_mine: true, is_read: false, reactions: [] }]);
                if (newId > lastMessageId) lastMessageId = newId;
                scrollBottom();
            })
            .catch(function () { showToast('Network error. Please try again.', 'error'); inputEl.value = text; });
    }

    // ── Typing ── //
    function sendTyping(state) {
        if (!activeUserId) return;
        post('/chat/typing', { receiver_id: activeUserId, typing: state ? 1 : 0 }).catch(function (err) { console.error("[chat]", err); });
    }

    function startTypingPoll(userId) {
        typingPollTimer = setInterval(function () {
            if (activeUserId !== userId) return;
            get('/chat/typing?with=' + userId)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.typing) {
                        typingLabel.textContent = activeUserName + ' is typing';
                        typingRow.classList.remove('dept-hidden');
                    } else {
                        typingRow.classList.add('dept-hidden');
                    }
                })
                .catch(function (err) { console.error("[chat]", err); });
        }, 2500);
    }

    function stopTypingPoll() {
        clearInterval(typingPollTimer);
        typingRow.classList.add('dept-hidden');
    }

    // ── Dropdown ── //
    function openDropdown() {
        dropdown.classList.remove('dept-hidden');
        menuBtn.setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
        dropdown.classList.add('dept-hidden');
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    function updateMuteLabel() {
        if (!muteBtn) return;
        var muted = mutedUsers[activeUserId];
        muteBtn.innerHTML = muted
            ? '<i class="ti ti-bell"></i> Unmute conversation'
            : '<i class="ti ti-bell-off"></i> Mute conversation';
    }

    menuBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!activeUserId) return;
        dropdown.classList.contains('dept-hidden') ? openDropdown() : closeDropdown();
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && e.target !== menuBtn) closeDropdown();
    });

    muteBtn.addEventListener('click', function () {
        closeDropdown();
        if (!activeUserId) return;
        mutedUsers[activeUserId] = !mutedUsers[activeUserId];
        isMuted = mutedUsers[activeUserId];
        updateMuteLabel();
        showToast(isMuted ? activeUserName + ' muted.' : activeUserName + ' unmuted.', 'info');
    });

    // ── View profile ── //
    viewProfileBtn.addEventListener('click', function () {
        closeDropdown();
        if (!activeUserId) return;

        if (activeUserAvatar && !activeUserAvatar.includes('default-avatar')) {
            profileAvatar.innerHTML = '<img src="' + esc(activeUserAvatar) + '" alt="" class="chat-profile-avatar-img">';
        } else {
            profileAvatar.innerHTML = '<div class="chat-conv-initials chat-profile-avatar-initials">' + esc(avatarInitials(activeUserName)) + '</div>';
        }
        profileName.textContent = activeUserName;
        profileDept.textContent = activeUserDept || '\u2014';

        var user = allUsers.find(function (u) { return u.id === activeUserId; });
        profileStatus.innerHTML = isOnline(user ? user.last_seen_at : null)
            ? '<span class="chat-online-badge">\u25CF Online</span>'
            : 'Offline';

        profileModal.classList.remove('dept-hidden');
    });

    profileClose.addEventListener('click', function () {
        profileModal.classList.add('dept-hidden');
    });

    profileModal.addEventListener('click', function (e) {
        if (e.target === profileModal) profileModal.classList.add('dept-hidden');
    });

    // ── Block ── //
    blockBtn.addEventListener('click', function () {
        closeDropdown();
        if (!activeUserId) return;
        showBlockModal();
    });

    function updateBlockState(blocked) {
        isBlocked = blocked;
        if (blocked) {
            blockedNotice.classList.remove('dept-hidden');
            inputBar.classList.add('dept-hidden');
        } else {
            blockedNotice.classList.add('dept-hidden');
            inputBar.classList.remove('dept-hidden');
        }
    }

    function showBlockModal() {
        blockModalName.textContent = activeUserName;
        blockModal.classList.remove('dept-hidden');
    }

    function hideBlockModal() {
        blockModal.classList.add('dept-hidden');
    }

    blockCancel.addEventListener('click', hideBlockModal);
    blockModal.addEventListener('click', function (e) {
        if (e.target === blockModal) hideBlockModal();
    });

    blockConfirm.addEventListener('click', function () {
        hideBlockModal();
        post('/chat/block', { user_id: activeUserId })
            .then(function () {
                updateBlockState(true);
                showToast(activeUserName + ' has been blocked.', 'info');
            })
            .catch(function (err) { console.error("[chat]", err); });
    });

    unblockBtn.addEventListener('click', function () {
        if (!activeUserId) return;
        post('/chat/unblock', { user_id: activeUserId })
            .then(function () {
                updateBlockState(false);
                showToast(activeUserName + ' has been unblocked.', 'info');
            })
            .catch(function (err) { console.error("[chat]", err); });
    });

    // ── Toast ── //
    function showToast(msg, type) {
        var t = document.createElement('div');
        t.className = 'chat-toast chat-toast--' + (type || 'info');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function () { t.classList.add('chat-toast--show'); }, 10);
        setTimeout(function () {
            t.classList.remove('chat-toast--show');
            setTimeout(function () { t.remove(); }, 300);
        }, 3000);
    }

    // ── Topbar badge ── //
    function pollUnreadBadge() {
        get('/chat/unread')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var dot = document.getElementById('chatDot');
                if (dot) dot.classList.toggle('d-none', !(data.unread > 0));
                var badge = document.getElementById('chatBadge');
                var sidebarBadge = document.getElementById('chatSidebarBadge');
                if (sidebarBadge) {
                    sidebarBadge.textContent = data.unread;
                    sidebarBadge.classList.toggle('d-none', !(data.unread > 0));
                }
                if (badge) {
                    badge.textContent = data.unread;
                    badge.classList.toggle('d-none', !(data.unread > 0));
                }
            })
            .catch(function (err) { console.error("[chat]", err); });
    }

    // ── Event bindings ── //
    sendBtn.addEventListener('click', sendMessage);

    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    inputEl.addEventListener('input', function () {
        ArsStyle.setVars(inputEl, { height: 'auto' });
        ArsStyle.setVars(inputEl, { height: Math.min(inputEl.scrollHeight, 120) + 'px' });
        charCount.textContent = inputEl.value.length + ' / 2000';
        sendTyping(true);
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function () { sendTyping(false); }, 2000);
    });

    searchInput.addEventListener('input', function () {
        if (allUsers.length === 0) {
            loadUsers(); // retry fetch if list was empty
        } else {
            renderUserList(allUsers);
        }
    });

    backBtn.addEventListener('click', function () {
        chatLayout.classList.remove('chat-layout--open');
                activeUserId = null;
        clearInterval(pollTimer);
        stopTypingPoll();
        conversation.classList.add('dept-hidden');
        welcome.classList.remove('dept-hidden');
    });

    loadMoreBtn.addEventListener('click', function () {
        if (!activeUserId || allMsgLoaded) return;
        loadMessages(activeUserId, false, oldestMsgId);
    });

    // ── Mobile swipe ── //
    (function initSwipe() {
        var touchStartX = 0;
        var touchStartY = 0;

        chatLayout.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        chatLayout.addEventListener('touchend', function (e) {
            if (!chatLayout.classList.contains('chat-layout--open')) return;
            var dx = e.changedTouches[0].clientX - touchStartX;
            var dy = e.changedTouches[0].clientY - touchStartY;
            if (dx > 60 && Math.abs(dy) < Math.abs(dx) * 0.6) backBtn.click();
        }, { passive: true });
    })();

    // ── Sticker picker ── //
    stickerBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        stickerPicker.classList.toggle('dept-hidden');
    });

    document.addEventListener('click', function (e) {
        if (!stickerPicker.contains(e.target) && e.target !== stickerBtn)
            stickerPicker.classList.add('dept-hidden');
    });

    stickerPicker.addEventListener('click', function (e) {
        var btn = e.target.closest('.chat-sticker-btn');
        if (!btn || !activeUserId || isBlocked) return;
        var sticker = btn.dataset.sticker;
        stickerPicker.classList.add('dept-hidden');

        post('/chat/send', { receiver_id: activeUserId, message: sticker, message_type: 'sticker' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.error) { showToast('Failed to send sticker', 'error'); return; }
                var newId = parseInt(res.id);
                noMessages.classList.add('dept-hidden');
                appendMessages([{ id: newId, message: sticker, message_type: 'sticker', sent_at: res.sent_at, is_mine: true, is_read: false, reactions: [] }]);
                if (newId > lastMessageId) lastMessageId = newId;
                scrollBottom();
            })
            .catch(function () { showToast('Network error.', 'error'); });
    });

    // ── Attachments ── //
    attachBtn.addEventListener('click', function () {
        if (!activeUserId || isBlocked) return;
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        var file = fileInput.files[0];
        if (!file || !activeUserId) return;
        fileInput.value = '';

        var fd = new FormData();
        fd.append('file', file);
        fd.append('receiver_id', activeUserId);
        fd.append('csrf_token', csrf());

        fetch(BASE + '/chat/upload', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.error) { showToast('Upload failed: ' + res.error, 'error'); return; }
                var newId = parseInt(res.id);
                noMessages.classList.add('dept-hidden');
                appendMessages([{ id: newId, message: res.filename, message_type: 'attachment', attachment_url: res.url, sent_at: res.sent_at, is_mine: true, is_read: false, reactions: [] }]);
                if (newId > lastMessageId) lastMessageId = newId;
                scrollBottom();
            })
            .catch(function () { showToast('Network error.', 'error'); });
    });

    // ── Init ── //
    loadUsers();
    setInterval(loadUsers, 2000);
    pollUnreadBadge();
    unreadTimer = setInterval(pollUnreadBadge, 30000);

    if (window.location.hash.startsWith('#user=')) {
        var targetId = parseInt(window.location.hash.replace('#user=', ''));
        if (targetId) {
            var opener = setInterval(function () {
                var el = userList.querySelector('[data-id="' + targetId + '"]');
                if (el) { el.click(); clearInterval(opener); }
            }, 200);
        }
    }
})();