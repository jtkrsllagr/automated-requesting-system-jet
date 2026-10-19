<?php if (!defined('BASE_LOADED')) throw new RuntimeException('Direct access not allowed.') ?>

<div class="chat-layout" id="chatLayout">

    <!-- ── LEFT: user list ── -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="chat-sidebar-header">
            <span class="chat-sidebar-title">Messages</span>
            <div class="chat-search-wrap">
                <i class="ti ti-search"></i>
                <input type="text" id="chatSearch" placeholder="Search people or departments…" autocomplete="off">
            </div>
            <div class="chat-filter-row" id="chatFilterRow">
                <button class="chat-filter-pill chat-filter-pill--active" data-filter="all">All</button>
                <button class="chat-filter-pill" data-filter="unread">Unread</button>
                <button class="chat-filter-pill" data-filter="online">Online</button>
            </div>
        </div>
        <div class="chat-user-list" id="chatUserList">
            <div class="chat-empty-state">
                <i class="ti ti-loader-2 ti-spin"></i>
                <span>Loading…</span>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: conversation ── -->
    <div class="chat-main" id="chatMain">

        <!-- Welcome -->
        <div class="chat-welcome" id="chatWelcome">
            <div class="chat-unread-banner dept-hidden" id="chatUnreadBanner">
                <i class="ti ti-bell-ringing"></i>
                <span id="chatUnreadBannerText"></span>
            </div>
            <i class="ti ti-messages chat-welcome-icon"></i>
            <h3>Start a conversation</h3>
            <p>Pick a colleague on the left to open a chat.</p>
            <button class="chat-welcome-cta" id="chatWelcomeCta">
                <i class="ti ti-users chat-cta-icon"></i>Browse colleagues
            </button>
        </div>

        <!-- Active conversation -->
        <div class="chat-conversation dept-hidden" id="chatConversation">

            <!-- Header -->
            <div class="chat-conv-header" id="chatConvHeader">
                <button class="icon-btn chat-back-btn" id="chatBackBtn" title="Back">
                    <i class="ti ti-arrow-left"></i>
                </button>
                <div class="chat-conv-avatar" id="chatConvAvatar"></div>
                <div class="chat-conv-info">
                    <div class="chat-conv-name" id="chatConvName"></div>
                    <div class="chat-conv-status" id="chatConvStatus"></div>
                </div>
                <div class="chat-conv-actions">
                    <button class="icon-btn chat-menu-btn" id="chatMenuBtn" title="More options"
                            aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots-vertical"></i>
                    </button>
                    <div class="chat-dropdown dept-hidden" id="chatDropdown" role="menu">
                        <button class="chat-dropdown-item" id="chatViewProfileBtn" role="menuitem">
                            <i class="ti ti-user-circle"></i> View profile
                        </button>
                        <button class="chat-dropdown-item" id="chatMuteBtn" role="menuitem">
                            <i class="ti ti-bell-off"></i> Mute conversation
                        </button>
                        <button class="chat-dropdown-item chat-dropdown-item--danger" id="chatBlockBtn" role="menuitem">
                            <i class="ti ti-ban"></i> Block user
                        </button>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages" id="chatMessages">
                <div class="chat-load-more dept-hidden" id="chatLoadMore">
                    <button class="chat-load-more-btn" id="chatLoadMoreBtn">
                        <i class="ti ti-clock-history"></i> Load older messages
                    </button>
                </div>
                <div class="chat-empty-state dept-hidden" id="chatNoMessages">
                    <i class="ti ti-message-2-plus"></i>
                    <span>No messages yet — say hello!</span>
                </div>
            </div>

            <!-- Typing -->
            <div class="chat-typing-row dept-hidden" id="chatTypingRow">
                <span class="chat-typing-label" id="chatTypingLabel"></span>
                <span class="chat-typing-dots"><span></span><span></span><span></span></span>
            </div>

            <!-- Blocked -->
            <div class="chat-blocked-notice dept-hidden" id="chatBlockedNotice">
                <i class="ti ti-ban"></i> You can't message this person.
                <button class="btn-link" id="chatUnblockBtn">Unblock</button>
            </div>
            
            <!-- Input -->
            <div class="chat-input-bar" id="chatInputBar">
                <!-- Sticker picker -->
                <div class="chat-sticker-picker dept-hidden" id="chatStickerPicker">
                    <?php
                    $stickers = ['😀','😂','😍','🥰','😎','🤔','😅','🤣','😭','😡','👍','👎','❤️','🔥','🎉','🙏','👏','💪','🤝','✅'];
                    foreach ($stickers as $s): ?>
                        <button class="chat-sticker-btn" data-sticker="<?= $s ?>"><?= $s ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="chat-input-row">
                    <button class="icon-btn chat-attach-btn" id="chatAttachBtn" title="Attach file">
                        <i class="ti ti-paperclip"></i>
                    </button>
                    <button class="icon-btn chat-sticker-toggle" id="chatStickerBtn" title="Add sticker">
                        <i class="ti ti-mood-smile"></i>
                    </button>
                    <input type="file" id="chatFileInput" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" class="hidden-input">
                    <textarea id="chatInput" class="chat-textarea"
                        placeholder="Write a message…" rows="1" maxlength="2000"></textarea>
                    <button class="chat-send-btn" id="chatSendBtn" title="Send (Enter)">
                        <i class="ti ti-send-2"></i>
                    </button>
                </div>
                <div class="chat-input-footer">
                    <span class="chat-input-hint">Enter to send &middot; Shift+Enter for new line</span>
                    <span class="chat-char-count" id="chatCharCount">0 / 2000</span>
                </div>
            </div>

        </div><!-- /chat-conversation -->
    </div><!-- /chat-main -->
</div><!-- /chat-layout -->

<!-- Mobile swipe hint -->
<div class="chat-swipe-hint dept-hidden" id="chatSwipeHint" role="status" aria-live="polite">
    <i class="ti ti-arrow-left"></i> Swipe right to go back
</div>

<!-- Block modal -->
<div class="chat-modal-overlay dept-hidden" id="chatBlockModal">
    <div class="chat-modal">
        <div class="chat-modal-icon"><i class="ti ti-ban"></i></div>
        <h4 class="chat-modal-title">Block <span id="chatBlockModalName"></span>?</h4>
        <p class="chat-modal-desc">You won't be able to send or receive messages from this person.</p>
        <div class="chat-modal-actions">
            <button class="chat-modal-cancel" id="chatBlockCancel">Cancel</button>
            <button class="chat-modal-confirm" id="chatBlockConfirm">Block</button>
        </div>
    </div>
</div>

<!-- Profile modal -->
<div class="chat-modal-overlay dept-hidden" id="chatProfileModal">
    <div class="chat-modal chat-profile-modal">
        <button class="chat-profile-close" id="chatProfileClose" title="Close">
            <i class="ti ti-x"></i>
        </button>
        <div class="chat-profile-avatar" id="chatProfileAvatar"></div>
        <div class="chat-profile-name" id="chatProfileName"></div>
        <div class="chat-profile-dept" id="chatProfileDept"></div>
        <div class="chat-profile-status" id="chatProfileStatus"></div>
    </div>
</div>