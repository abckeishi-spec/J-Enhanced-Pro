/**
 * JGrants Integration Admin JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Test API connection
        $('#test-api-connection').on('click', function() {
            var $button = $(this);
            var $result = $('#test-result');
            
            $button.prop('disabled', true);
            $result.text('テスト中...').removeClass('success error');
            
            $.ajax({
                url: jgrants_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jgrants_test_connection',
                    nonce: jgrants_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.text('✓ ' + response.data.message).addClass('success');
                    } else {
                        $result.text('✗ ' + response.data.message).addClass('error');
                    }
                },
                error: function() {
                    $result.text('✗ 接続テストに失敗しました').addClass('error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Sync now button
        $('#sync-now').on('click', function() {
            if (!confirm(jgrants_admin.strings.confirm_sync)) {
                return;
            }
            
            var $button = $(this);
            var $status = $('#sync-status');
            
            $button.prop('disabled', true);
            $status.html('<span class="jgrants-spinner"></span> ' + jgrants_admin.strings.syncing)
                   .removeClass('success error')
                   .addClass('syncing');
            
            $.ajax({
                url: jgrants_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jgrants_sync_now',
                    nonce: jgrants_admin.nonce
                },
                timeout: 300000, // 5 minutes timeout
                success: function(response) {
                    if (response.success) {
                        $status.html('✓ ' + response.data.message)
                               .removeClass('syncing error')
                               .addClass('success');
                        
                        // Reload page after 2 seconds to show updated history
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $status.html('✗ ' + jgrants_admin.strings.sync_error + ': ' + response.data.message)
                               .removeClass('syncing success')
                               .addClass('error');
                    }
                },
                error: function(xhr, status, error) {
                    var message = status === 'timeout' ? 'タイムアウト' : error;
                    $status.html('✗ ' + jgrants_admin.strings.sync_error + ': ' + message)
                           .removeClass('syncing success')
                           .addClass('error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Regenerate AI content button
        $('#regenerate-ai-content').on('click', function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            
            if (!postId) {
                return;
            }
            
            $button.prop('disabled', true).addClass('loading');
            $button.text('生成中...');
            
            $.ajax({
                url: jgrants_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jgrants_regenerate_content',
                    post_id: postId,
                    nonce: jgrants_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.text('✓ 生成完了');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $button.text('✗ 生成失敗');
                        alert('エラー: ' + response.data.message);
                    }
                },
                error: function() {
                    $button.text('✗ エラー');
                    alert('AIコンテンツの生成中にエラーが発生しました。');
                },
                complete: function() {
                    setTimeout(function() {
                        $button.prop('disabled', false).removeClass('loading');
                        $button.text('今すぐAIコンテンツを生成');
                    }, 2000);
                }
            });
        });
        
        // Auto-save prompt changes
        var promptTimeout;
        $('textarea[name*="_prompt"]').on('input', function() {
            var $textarea = $(this);
            clearTimeout(promptTimeout);
            
            promptTimeout = setTimeout(function() {
                // Show saving indicator
                $textarea.css('border-color', '#f39c12');
                
                // Auto-save logic here if needed
                setTimeout(function() {
                    $textarea.css('border-color', '#ddd');
                }, 1000);
            }, 1000);
        });
        
        // Settings tab navigation (if tabs are implemented)
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var $tab = $(this);
            var target = $tab.attr('href');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            $('.settings-panel').hide();
            $(target).show();
        });
        
        // Confirm before leaving page with unsaved changes
        var formChanged = false;
        $('form').on('change', 'input, textarea, select', function() {
            formChanged = true;
        });
        
        $('form').on('submit', function() {
            formChanged = false;
        });
        
        $(window).on('beforeunload', function() {
            if (formChanged) {
                return '変更が保存されていません。このページを離れますか？';
            }
        });
        
        // Toggle advanced settings
        $('.toggle-advanced-settings').on('click', function() {
            var $button = $(this);
            var $settings = $('.advanced-settings');
            
            if ($settings.is(':visible')) {
                $settings.slideUp();
                $button.text('詳細設定を表示');
            } else {
                $settings.slideDown();
                $button.text('詳細設定を非表示');
            }
        });
        
        // Copy to clipboard functionality
        $('.copy-to-clipboard').on('click', function() {
            var $button = $(this);
            var text = $button.data('clipboard-text');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    var originalText = $button.text();
                    $button.text('✓ コピーしました');
                    setTimeout(function() {
                        $button.text(originalText);
                    }, 2000);
                });
            }
        });
        
        // Initialize tooltips if any
        if ($.fn.tooltip) {
            $('.has-tooltip').tooltip();
        }
        
        // Format numbers with commas
        $('.format-number').each(function() {
            var $element = $(this);
            var number = parseInt($element.text());
            if (!isNaN(number)) {
                $element.text(number.toLocaleString());
            }
        });
        
        // Initialize date pickers if needed
        if ($.fn.datepicker) {
            $('.datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            });
        }
        
    });
    
})(jQuery);