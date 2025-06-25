/**
 * Claude Chatbot - Main JavaScript functionality
 */

class ClaudeChatbot {
    constructor(options = {}) {
        // Default options
        this.options = {
            apiEndpoint: 'api.php',
            messageLimit: 50,
            historyLimit: 10,
            typingDelay: true,
            markdownSupport: true,
            ...options
        };
        
        // DOM Elements
        this.chatMessages = document.getElementById('chat-messages');
        this.chatInput = document.getElementById('chat-input');
        this.sendButton = document.getElementById('send-button');
        
        // State
        this.conversationHistory = [];
        this.isProcessing = false;
        
        // Bind event listeners
        this.bindEvents();
    }
    
    /**
     * Bind all event listeners
     */
    bindEvents() {
        // Send button click
        this.sendButton.addEventListener('click', () => this.handleSend());
        
        // Enter key press
        this.chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.handleSend();
            }
        });
        
        // Input changes (for handling typing state)
        this.chatInput.addEventListener('input', () => {
            this.updateSendButtonState();
        });
    }
    
    /**
     * Handle send button click or Enter key press
     */
    handleSend() {
        if (this.isProcessing) return;
        
        const message = this.chatInput.value.trim();
        if (!message) return;
        
        // Add user message to chat
        this.addUserMessage(message);
        
        // Clear input
        this.chatInput.value = '';
        this.updateSendButtonState();
        
        // Send message to API
        this.sendToApi(message);
    }
    
    /**
     * Update send button state based on input and processing state
     */
    updateSendButtonState() {
        this.sendButton.disabled = this.isProcessing || !this.chatInput.value.trim();
    }
    
    /**
     * Add a user message to the chat
     */
    addUserMessage(content) {
        this.addMessage(content, 'user');
        
        // Add to conversation history
        this.conversationHistory.push({
            role: 'user',
            content: content
        });
        
        // Trim history if needed
        this.trimConversationHistory();
    }
    
    /**
     * Add a bot message to the chat
     */
    addBotMessage(content) {
        this.addMessage(content, 'bot');
        
        // Add to conversation history
        this.conversationHistory.push({
            role: 'assistant',
            content: content
        });
        
        // Trim history if needed
        this.trimConversationHistory();
    }
    
    /**
     * Add a message to the chat (generic)
     */
    addMessage(content, sender) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('message', `${sender}-message`);
        
        // Process content (markdown etc.) if it's a bot message
        if (sender === 'bot' && this.options.markdownSupport) {
            content = this.processMarkdown(content);
        }
        
        messageElement.innerHTML = content;
        this.chatMessages.appendChild(messageElement);
        
        // Limit the number of messages in the DOM
        this.trimMessageElements();
        
        // Scroll to the latest message
        this.scrollToBottom();
        
        return messageElement;
    }
    
    /**
     * Process markdown in the content
     */
    processMarkdown(content) {
        // Convert code blocks
        content = content.replace(/```(\w+)?\n([\s\S]*?)```/g, (match, language, code) => {
            const langClass = language ? ` class="language-${language}"` : '';
            return `<pre><code${langClass}>${this.escapeHtml(code)}</code></pre>`;
        });
        
        // Convert inline code
        content = content.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Convert bold
        content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        
        // Convert italic
        content = content.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        
        // Convert links
        content = content.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        
        // Convert headers
        content = content.replace(/^### (.*$)/gm, '<h3>$1</h3>');
        content = content.replace(/^## (.*$)/gm, '<h2>$1</h2>');
        content = content.replace(/^# (.*$)/gm, '<h1>$1</h1>');
        
        // Convert lists
        content = content.replace(/^\s*\* (.*$)/gm, '<li>$1</li>');
        content = content.replace(/(<li>.*<\/li>)/gms, '<ul>$1</ul>');
        
        // Convert numbered lists
        content = content.replace(/^\s*\d+\. (.*$)/gm, '<li>$1</li>');
        content = content.replace(/(<li>.*<\/li>)/gms, function(match) {
            return match.startsWith('<ul>') ? match : '<ol>' + match + '</ol>';
        });
        
        // Convert paragraphs (needs to come last)
        content = content.split('\n\n').map(para => {
            // Skip if it's already HTML
            if (para.trim().startsWith('<')) return para;
            return `<p>${para}</p>`;
        }).join('');
        
        return content;
    }
    
    /**
     * Escape HTML in code blocks
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    /**
     * Trim conversation history to limit
     */
    trimConversationHistory() {
        if (this.conversationHistory.length > this.options.historyLimit) {
            this.conversationHistory = this.conversationHistory.slice(
                this.conversationHistory.length - this.options.historyLimit
            );
        }
    }
    
    /**
     * Trim message elements in the DOM
     */
    trimMessageElements() {
        const messages = this.chatMessages.querySelectorAll('.message');
        if (messages.length > this.options.messageLimit) {
            for (let i = 0; i < messages.length - this.options.messageLimit; i++) {
                messages[i].remove();
            }
        }
    }
    
    /**
     * Scroll to the bottom of the chat
     */
    scrollToBottom() {
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }
    
    /**
     * Show typing indicator
     */
    showTypingIndicator() {
        // Remove any existing indicator first
        this.hideTypingIndicator();
        
        const typingIndicator = document.createElement('div');
        typingIndicator.classList.add('typing-indicator');
        typingIndicator.id = 'typing-indicator';
        
        for (let i = 0; i < 3; i++) {
            const dot = document.createElement('div');
            dot.classList.add('typing-dot');
            typingIndicator.appendChild(dot);
        }
        
        this.chatMessages.appendChild(typingIndicator);
        this.scrollToBottom();
        
        return typingIndicator;
    }
    
    /**
     * Hide typing indicator
     */
    hideTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }
    
    /**
     * Send message to API
     */
    async sendToApi(message) {
        try {
            // Set processing state
            this.isProcessing = true;
            this.chatInput.disabled = true;
            this.updateSendButtonState();
            
            // Show typing indicator
            if (this.options.typingDelay) {
                this.showTypingIndicator();
            }
            
            // Send request to the server
            const response = await fetch(this.options.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    history: this.conversationHistory
                })
            });
            
            // Hide typing indicator
            this.hideTypingIndicator();
            
            if (!response.ok) {
                throw new Error(`Server error: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Add Claude's response to the chat
            this.addBotMessage(data.message);
            
        } catch (error) {
            console.error('API Error:', error);
            this.hideTypingIndicator();
            this.addBotMessage('Sorry, I encountered an error processing your request. Please try again later.');
        } finally {
            // Reset processing state
            this.isProcessing = false;
            this.chatInput.disabled = false;
            this.updateSendButtonState();
            this.chatInput.focus();
        }
    }
    
    /**
     * Clear the chat history
     */
    clearChat() {
        // Clear DOM
        while (this.chatMessages.firstChild) {
            this.chatMessages.removeChild(this.chatMessages.firstChild);
        }
        
        // Reset conversation history
        this.conversationHistory = [];
        
        // Add welcome message
        this.addBotMessage('Hello! I\'m a chatbot powered by Claude. How can I help you today?');
    }
}

// Initialize the chatbot when the page loads
document.addEventListener('DOMContentLoaded', function() {
    window.chatbot = new ClaudeChatbot({
        messageLimit: 50,
        historyLimit: 10,
        typingDelay: true
    });
});