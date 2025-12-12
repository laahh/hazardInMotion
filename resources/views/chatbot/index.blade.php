@extends('layouts.master')

@section('title', 'Chatbot AI')

@section('css')
<link href="{{ URL::asset('build/plugins/apexchart/apexcharts.min.css') }}" rel="stylesheet" />
<style>
    .chatbot-wrapper {
        height: calc(100vh - 280px);
        min-height: 600px;
        display: flex;
        flex-direction: column;
    }

    .chatbot-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: #f8f9fa;
    }

    .chatbot-messages::-webkit-scrollbar {
        width: 6px;
    }

    .chatbot-messages::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .chatbot-messages::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }

    .message {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message.user {
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
        flex-shrink: 0;
    }

    .message.user .message-avatar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .message.assistant .message-avatar {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }

    .message-content {
        flex: 1;
        max-width: 75%;
    }

    .message.user .message-content {
        text-align: right;
    }

    .message-bubble {
        display: inline-block;
        padding: 12px 16px;
        border-radius: 12px;
        word-wrap: break-word;
        line-height: 1.6;
        font-size: 14px;
    }

    .message.user .message-bubble {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-bottom-right-radius: 4px;
    }

    .message.assistant .message-bubble {
        background: white;
        color: #333;
        border: 1px solid #e5e7eb;
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .typing-indicator {
        display: flex;
        gap: 4px;
        padding: 12px 16px;
    }

    .typing-indicator span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #999;
        animation: typing 1.4s infinite;
    }

    .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {
        0%, 60%, 100% {
            transform: translateY(0);
            opacity: 0.7;
        }
        30% {
            transform: translateY(-10px);
            opacity: 1;
        }
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #6c757d;
        text-align: center;
        padding: 40px;
    }

    .empty-state-icon {
        font-size: 64px;
        margin-bottom: 16px;
        opacity: 0.5;
        color: #667eea;
    }

    .suggestions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 20px;
        justify-content: center;
    }

    .suggestion-chip {
        padding: 8px 16px;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .suggestion-chip:hover {
        background: #f8f9fa;
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
    }

    .chart-container {
        margin-top: 16px;
        padding: 16px;
        background: white;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .chart-container h6 {
        margin: 0 0 12px 0;
        color: #333;
        font-size: 14px;
        font-weight: 600;
    }

    .explanation-container {
        margin-top: 12px;
        padding: 16px;
        background: #f0f7ff;
        border-left: 4px solid #667eea;
        border-radius: 8px;
    }

    .explanation-container h6 {
        margin: 0 0 8px 0;
        color: #667eea;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .explanation-content {
        color: #555;
        font-size: 13px;
        line-height: 1.8;
    }

    .explanation-content strong {
        color: #667eea;
        font-weight: 600;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 24px;
        color: white;
        text-align: center;
        margin: 16px 0;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        animation: fadeIn 0.5s ease-in;
    }

    .stat-label {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 8px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 48px;
        font-weight: 700;
        line-height: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin: 16px 0;
    }

    .stat-item {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 16px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-item:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }

    .stat-item-label {
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 8px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-item-value {
        font-size: 28px;
        font-weight: 700;
        color: #667eea;
    }
</style>
@endsection

@section('content')
<x-page-title title="Chatbot AI" pagetitle="Asisten AI" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-header py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="wh-48 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                            <i class="material-icons-outlined">smart_toy</i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Asisten AI</h5>
                            <p class="mb-0 text-muted small">Tanyakan apapun tentang data CCTV</p>
                        </div>
                    </div>
                    <div>
                        <span class="badge bg-primary" id="model-badge">Siap</span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="chatbot-wrapper">
                    <!-- Messages Area -->
                    <div class="chatbot-messages" id="messages-container">
                        <div class="empty-state" id="empty-state">
                            <div class="empty-state-icon">
                                <i class="material-icons-outlined">chat_bubble_outline</i>
                            </div>
                            <h5 class="fw-bold">Mulai Percakapan</h5>
                            <p class="text-muted">Kirim pesan untuk memulai percakapan dengan AI</p>
                            <div class="suggestions">
                                <div class="suggestion-chip" onclick="sendSuggestion('Berapa total area kritis?')">
                                    Berapa total area kritis?
                                </div>
                                <div class="suggestion-chip" onclick="sendSuggestion('Tampilkan distribusi CCTV per site')">
                                    Distribusi CCTV per site
                                </div>
                                <div class="suggestion-chip" onclick="sendSuggestion('Overview area kritis')">
                                    Overview area kritis
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="card-footer bg-white border-top">
                        <div class="d-flex align-items-end gap-2">
                            <div class="flex-grow-1">
                                <textarea 
                                    id="message-input" 
                                    class="form-control" 
                                    placeholder="Ketik pesan Anda di sini..."
                                    rows="1"
                                    style="resize: none; min-height: 48px; max-height: 200px;"
                                ></textarea>
                            </div>
                            <button 
                                id="send-btn" 
                                class="btn btn-primary rounded-circle"
                                style="width: 48px; height: 48px;"
                                onclick="sendMessage()"
                            >
                                <i class="material-icons-outlined">send</i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ URL::asset('build/plugins/apexchart/apexcharts.min.js') }}"></script>
<script>
    const messagesContainer = document.getElementById('messages-container');
    const messageInput = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const emptyState = document.getElementById('empty-state');
    const modelBadge = document.getElementById('model-badge');
    
    let conversationHistory = [];
    let isProcessing = false;

    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 200) + 'px';
    });

    // Send on Enter (Shift+Enter for new line)
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function sendSuggestion(text) {
        messageInput.value = text;
        sendMessage();
    }

    function sendMessage() {
        const message = messageInput.value.trim();
        
        if (!message || isProcessing) {
            return;
        }

        // Hide empty state
        if (emptyState) {
            emptyState.style.display = 'none';
        }

        // Add user message to UI
        addMessage('user', message);
        
        // Clear input
        messageInput.value = '';
        messageInput.style.height = 'auto';
        
        // Disable input
        setProcessing(true);

        // Add typing indicator
        const typingId = addTypingIndicator();

        // Add to conversation history
        conversationHistory.push({
            role: 'user',
            content: message
        });

        // Send to server
        fetch('{{ route("chatbot.send") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                message: message,
                conversation_history: conversationHistory
            })
        })
        .then(response => response.json())
        .then(data => {
            removeTypingIndicator(typingId);
            
            if (data.success) {
                // Add assistant message to UI with chart and explanation
                let messageContent = data.message || '';
                
                // Gabungkan explanation dengan ai_reasoning jika ada
                let fullExplanation = data.explanation || '';
                if (data.ai_reasoning && fullExplanation) {
                    fullExplanation = `**Alasan Pemilihan Rule:**\n${data.ai_reasoning}\n\n---\n\n${fullExplanation}`;
                } else if (data.ai_reasoning) {
                    fullExplanation = `**Alasan Pemilihan Rule:**\n${data.ai_reasoning}`;
                }
                
                addMessage('assistant', messageContent, data.chart, fullExplanation);
                
                // Add to conversation history
                conversationHistory.push({
                    role: 'assistant',
                    content: messageContent
                });

                // Update model badge
                if (data.model) {
                    modelBadge.textContent = data.model;
                    modelBadge.className = 'badge bg-success';
                }
            } else {
                addMessage('assistant', data.message || 'Terjadi kesalahan. Silakan coba lagi.');
            }
        })
        .catch(error => {
            removeTypingIndicator(typingId);
            console.error('Error:', error);
            addMessage('assistant', 'Maaf, terjadi kesalahan saat mengirim pesan. Silakan coba lagi.');
        })
        .finally(() => {
            setProcessing(false);
            messageInput.focus();
        });
    }

    function addMessage(role, content, chartData = null, explanation = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        
        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.textContent = role === 'user' ? 'U' : 'AI';
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        
        // Format message (support markdown-like formatting)
        // Check jika content sudah mengandung HTML tags lengkap
        const hasHtmlTags = /<[^>]+>.*<\/[^>]+>/.test(content) || content.trim().startsWith('<');
        
        if (hasHtmlTags) {
            // HTML sudah ada, langsung set tanpa processing
            bubble.innerHTML = content;
        } else {
            // Process markdown
            bubble.innerHTML = formatMessage(content);
        }
        
        // Add chart if available
        if (chartData && typeof ApexCharts !== 'undefined') {
            const chartId = 'chart-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const chartContainer = document.createElement('div');
            chartContainer.className = 'chart-container';
            chartContainer.innerHTML = '<h6><i class="material-icons-outlined me-2" style="font-size: 16px; vertical-align: middle;">bar_chart</i>Visualisasi Data</h6><div id="' + chartId + '"></div>';
            bubble.appendChild(chartContainer);
            
            // Render chart after DOM is ready
            setTimeout(() => {
                const chartElement = document.getElementById(chartId);
                if (chartElement && chartData.config && !chartElement._apexcharts) {
                    const chart = new ApexCharts(chartElement, chartData.config);
                    chart.render();
                }
            }, 200);
        }
        
        // Add AI explanation if available
        if (explanation) {
            const explanationDiv = document.createElement('div');
            explanationDiv.className = 'explanation-container';
            
            let explanationContent = `<h6><i class="material-icons-outlined me-2" style="font-size: 16px; vertical-align: middle;">psychology</i>Analisis & Rekomendasi AI</h6>`;
            explanationContent += `<div class="explanation-content">${formatMessage(explanation)}</div>`;
            
            explanationDiv.innerHTML = explanationContent;
            bubble.appendChild(explanationDiv);
        }
        
        contentDiv.appendChild(bubble);
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(contentDiv);
        
        messagesContainer.appendChild(messageDiv);
        scrollToBottom();
    }

    function formatMessage(text) {
        // Jika text sudah mengandung HTML tags lengkap, langsung return
        if (text.includes('<div') || text.includes('<table') || text.includes('<pre')) {
            return text;
        }
        
        // Jika tidak ada HTML, process sebagai markdown biasa
        let html = text;
        
        // Convert markdown tables
        const lines = html.split('\n');
        let inTable = false;
        let tableRows = [];
        let result = [];
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            
            // Check if this is a table row
            if (line.startsWith('|') && line.endsWith('|') && !line.match(/^\|[\s-:]+\|$/)) {
                if (!inTable) {
                    inTable = true;
                    tableRows = [];
                }
                const cells = line.split('|').map(c => c.trim()).filter(c => c);
                if (cells.length > 0) {
                    tableRows.push(cells);
                }
            } else {
                // End of table
                if (inTable && tableRows.length > 0) {
                    let tableHtml = '<table class="table table-sm table-bordered" style="margin: 12px 0; font-size: 13px;"><tbody>';
                    tableRows.forEach((row, idx) => {
                        if (idx === 0) {
                            // Header row
                            tableHtml += '<tr style="background: #f7f7f8;">' + 
                                row.map(cell => '<th style="padding: 8px;">' + escapeHtml(cell) + '</th>').join('') + 
                                '</tr>';
                        } else {
                            // Data row
                            tableHtml += '<tr>' + 
                                row.map(cell => '<td style="padding: 8px;">' + escapeHtml(cell) + '</td>').join('') + 
                                '</tr>';
                        }
                    });
                    tableHtml += '</tbody></table>';
                    result.push(tableHtml);
                    tableRows = [];
                    inTable = false;
                }
                
                if (line) {
                    result.push(escapeHtml(line));
                }
            }
        }
        
        // Handle remaining table
        if (inTable && tableRows.length > 0) {
            let tableHtml = '<table class="table table-sm table-bordered" style="margin: 12px 0; font-size: 13px;"><tbody>';
            tableRows.forEach((row, idx) => {
                if (idx === 0) {
                    tableHtml += '<tr style="background: #f7f7f8;">' + 
                        row.map(cell => '<th style="padding: 8px;">' + escapeHtml(cell) + '</th>').join('') + 
                        '</tr>';
                } else {
                    tableHtml += '<tr>' + 
                        row.map(cell => '<td style="padding: 8px;">' + escapeHtml(cell) + '</td>').join('') + 
                        '</tr>';
                }
            });
            tableHtml += '</tbody></table>';
            result.push(tableHtml);
        }
        
        html = result.join('<br>');
        
        // Convert code blocks
        html = html.replace(/```([\s\S]*?)```/g, '<pre style="background: #f4f4f4; padding: 12px; border-radius: 8px; overflow-x: auto; margin: 8px 0;"><code>$1</code></pre>');
        
        // Convert inline code
        html = html.replace(/`([^`]+)`/g, '<code style="background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-size: 13px;">$1</code>');
        
        // Convert bold
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        
        // Convert italic
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        
        return html;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function addTypingIndicator() {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message assistant';
        messageDiv.id = 'typing-indicator';
        
        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.textContent = 'AI';
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        
        const typing = document.createElement('div');
        typing.className = 'message-bubble typing-indicator';
        typing.innerHTML = '<span></span><span></span><span></span>';
        
        contentDiv.appendChild(typing);
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(contentDiv);
        
        messagesContainer.appendChild(messageDiv);
        scrollToBottom();
        
        return 'typing-indicator';
    }

    function removeTypingIndicator(id) {
        const indicator = document.getElementById(id);
        if (indicator) {
            indicator.remove();
        }
    }

    function setProcessing(processing) {
        isProcessing = processing;
        messageInput.disabled = processing;
        sendBtn.disabled = processing;
        
        if (processing) {
            sendBtn.classList.add('disabled');
        } else {
            sendBtn.classList.remove('disabled');
        }
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Focus input on load
    messageInput.focus();
</script>
@endsection
