<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_owner = true; // Flag to determine if current user is pet owner

// Initialize variables
$conversation_id = null;
$pet_id = null;
$founder_email = null;
$founder_name = null;
$pet_name = null;
$pet_status = null;

// Check if conversation ID is provided
if (isset($_GET['conversation']) && !empty($_GET['conversation'])) {
    $conversation_id = (int)$_GET['conversation'];
    
    // Get conversation details
    $stmt = $conn->prepare("
        SELECT c.*, p.name as pet_name, p.status as pet_status
        FROM conversations c
        JOIN pets p ON c.pet_id = p.id
        WHERE c.id = ? AND (c.owner_id = ? OR c.founder_email = ?)
    ");
    $stmt->bind_param("iis", $conversation_id, $user_id, $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conversation = $result->fetch_assoc();
        $pet_id = $conversation['pet_id'];
        $founder_email = $conversation['founder_email'];
        $founder_name = $conversation['founder_name'];
        $pet_name = $conversation['pet_name'];
        $pet_status = $conversation['pet_status'];
        
        // Determine if user is owner or founder
        $is_owner = ($conversation['owner_id'] == $user_id);
        
        // Mark messages as read for the appropriate user
        if ($is_owner) {
            // Owner is viewing, mark founder messages as read and reset owner unread count
            $stmt = $conn->prepare("
                UPDATE founder_messages 
                SET is_read = 1 
                WHERE pet_id = ? AND founder_email = ? AND is_read = 0
            ");
            $stmt->bind_param("is", $pet_id, $founder_email);
            $stmt->execute();
            
            $stmt = $conn->prepare("
                UPDATE conversations 
                SET owner_unread_count = 0 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $conversation_id);
            $stmt->execute();
        } else {
            // Founder is viewing, mark owner messages as read and reset founder unread count
            $stmt = $conn->prepare("
                UPDATE owner_messages 
                SET is_read = 1 
                WHERE conversation_id = ? AND is_read = 0
            ");
            $stmt->bind_param("i", $conversation_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("
                UPDATE conversations 
                SET founder_unread_count = 0 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $conversation_id);
            $stmt->execute();
        }
    }
}

// If no conversation ID, or conversation not found, get all conversations for the user
if ($conversation_id === null) {
    // Check if pet_id and founder_email are provided (used when starting a chat from pet details page)
    if (isset($_GET['pet_id']) && !empty($_GET['pet_id']) && isset($_GET['founder_email']) && !empty($_GET['founder_email'])) {
        $pet_id = (int)$_GET['pet_id'];
        $founder_email = $_GET['founder_email'];
        
        // Check if this is a valid pet
        $stmt = $conn->prepare("
            SELECT p.*, u.name as owner_name, u.email as owner_email, u.id as owner_id
            FROM pets p
            JOIN users u ON p.owner_id = u.id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $pet_id);
        $stmt->execute();
        $pet_result = $stmt->get_result();
        
        if ($pet_result->num_rows > 0) {
            $pet = $pet_result->fetch_assoc();
            
            // Determine if user is owner or founder based on the pet
            $is_owner = ($pet['owner_id'] == $user_id);
            
            // If user is trying to message their own pet, redirect to conversation list
            if ($is_owner && $founder_email == $_SESSION['email']) {
                header("Location: chat.php");
                exit();
            }
            
            // Check if a conversation already exists
            $stmt = $conn->prepare("
                SELECT id FROM conversations
                WHERE pet_id = ? AND founder_email = ?
            ");
            $stmt->bind_param("is", $pet_id, $founder_email);
            $stmt->execute();
            $conv_result = $stmt->get_result();
            
            if ($conv_result->num_rows > 0) {
                // If conversation exists, redirect to it
                $conversation = $conv_result->fetch_assoc();
                header("Location: chat.php?conversation=" . $conversation['id']);
                exit();
            } else {
                // Create a new conversation
                $founder_name = isset($_SESSION['name']) ? $_SESSION['name'] : $_SESSION['username']; // Support both old and new session variable
                
                $stmt = $conn->prepare("
                    INSERT INTO conversations (
                        pet_id,
                        founder_name,
                        founder_email,
                        owner_id,
                        owner_name,
                        owner_email,
                        last_message_time
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param(
                    "ississ",
                    $pet_id,
                    $founder_name,
                    $founder_email,
                    $pet['owner_id'],
                    $pet['owner_name'],
                    $pet['owner_email']
                );
                
                if ($stmt->execute()) {
                    $conversation_id = $conn->insert_id;
                    header("Location: chat.php?conversation=" . $conversation_id);
                    exit();
                }
            }
        }
    }
    
    // Get conversations for the user
    // For pet owners: Get all conversations where they are the owner
    if ($is_owner) {
        $stmt = $conn->prepare("
            SELECT 
                c.id as conversation_id,
                c.pet_id,
                c.founder_name,
                c.founder_email,
                c.owner_unread_count as unread_count,
                p.name as pet_name,
                p.status as pet_status,
                c.last_message_time
            FROM conversations c
            JOIN pets p ON c.pet_id = p.id
            WHERE c.owner_id = ?
            ORDER BY c.last_message_time DESC
        ");
        $stmt->bind_param("i", $user_id);
    } else {
        // For founders: Get all conversations where they are the founder
        $stmt = $conn->prepare("
            SELECT 
                c.id as conversation_id,
                c.pet_id,
                c.owner_name as founder_name,
                c.owner_email as founder_email,
                c.founder_unread_count as unread_count,
                p.name as pet_name,
                p.status as pet_status,
                c.last_message_time
            FROM conversations c
            JOIN pets p ON c.pet_id = p.id
            WHERE c.founder_email = ?
            ORDER BY c.last_message_time DESC
        ");
        $stmt->bind_param("s", $_SESSION['email']);
    }
    
    $stmt->execute();
    $conversations = $stmt->get_result();
    
    // If there's at least one conversation, default to the first one
    if ($conversations->num_rows > 0) {
        $first_conversation = $conversations->fetch_assoc();
        $conversation_id = $first_conversation['conversation_id'];
        $pet_id = $first_conversation['pet_id'];
        $founder_email = $first_conversation['founder_email'];
        $founder_name = $first_conversation['founder_name'];
        $pet_name = $first_conversation['pet_name'];
        $pet_status = $first_conversation['pet_status'];
        
        // Reset the result pointer to beginning
        $conversations->data_seek(0);
        
        // Only redirect if no specific conversation was requested
        if (!isset($_GET['conversation']) && !isset($_GET['pet_id'])) {
            header("Location: chat.php?conversation=" . $conversation_id);
            exit();
        }
    }
}

// Get chat history
$chat_history = [];
if ($conversation_id !== null) {
    // Get chat history by combining founder_messages and owner_messages
    $stmt = $conn->prepare("
        (SELECT 
            'founder' as message_type,
            id,
            message, 
            created_at,
            founder_name,
            founder_email,
            is_read
        FROM founder_messages 
        WHERE conversation_id = ?)
        UNION 
        (SELECT 
            'owner' as message_type,
            id,
            message, 
            created_at,
            NULL as founder_name,
            NULL as founder_email,
            is_read
        FROM owner_messages 
        WHERE conversation_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("ii", $conversation_id, $conversation_id);
    $stmt->execute();
    $chat_history = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - PetQuest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="css/messages.css">
    <link rel="stylesheet" href="css/chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chat-container {
            display: flex;
            height: calc(100vh - 80px);
        }
        
        .conversations-sidebar {
            width: 1000px;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            background-color: #f9f9f9;
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .conversation-item:hover {
            background-color: #f0f0f0;
        }
        
        .conversation-item.active {
            background-color: #e0f0ff;
            border-left: 3px solid #3498db;
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .conversation-name {
            font-weight: 600;
            color: #333;
        }
        
        .conversation-time {
            font-size: 0.8rem;
            color: #777;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #fff;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background-color: #f9f9f9;
        }
        
        .message {
            margin-bottom: 15px;
            max-width: 70%;
            clear: both;
        }
        
        .message.founder {
            float: left;
        }
        
        .message.owner {
            float: right;
        }
        
        .message-content {
            padding: 10px 15px;
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message.founder .message-content {
            background-color: #f0f0f0;
            color: #333;
            border-bottom-left-radius: 0;
        }
        
        .message.owner .message-content {
            background-color: #3498db;
            color: #fff;
            border-bottom-right-radius: 0;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: #777;
            margin-top: 5px;
            text-align: right;
        }
        
        .message.founder .message-time {
            text-align: left;
        }
        
        .chat-input {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
            background-color: #fff;
        }
        
        .chat-input form {
            display: flex;
            align-items: center;
            background-color: #f5f5f5;
            border-radius: 24px;
            padding: 4px 8px 4px 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .chat-input form:focus-within {
            background-color: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            border: 1px solid #3498db;
        }
        
        .chat-input textarea {
            flex: 1;
            padding: 8px 0;
            border: none;
            background: transparent;
            resize: none;
            min-height: 24px;
            max-height: 80px;
            overflow-y: auto;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            outline: none;
        }
        
        .chat-input textarea::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }
        
        .chat-input button {
            background-color: #3498db;
            color: #fff;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-left: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: scale(1);
        }
        
        .chat-input button:hover {
            background-color: #2980b9;
            transform: scale(1.05);
        }
        
        .chat-input button:active {
            transform: scale(0.95);
        }
        
        .chat-input button i {
            font-size: 0.9rem;
        }
        
        .unread-badge {
            display: inline-block;
            background-color: #e74c3c;
            color: #fff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            line-height: 20px;
            text-align: center;
            margin-left: 5px;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        
        <main class="main-content">
            <div class="chat-container">
                <div class="conversations-sidebar">
                
                <div class="chat-main" id="chat-main-container">
                    <?php if ($conversation_id !== null): ?>
                        <div class="chat-header">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h2><?php echo $is_owner ? htmlspecialchars($founder_name) : htmlspecialchars($conversation['owner_name'] ?? "Unknown Owner"); ?></h2>
                                <div>
                                    <span><?php echo htmlspecialchars($pet_name); ?></span>
                                    <span class="pet-status status-<?php echo strtolower($pet_status); ?>">
                                        <?php echo ucfirst($pet_status); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chat-messages">
                            <?php if ($chat_history && $chat_history->num_rows > 0): ?>
                                <?php while ($chat = $chat_history->fetch_assoc()): ?>
                                    <?php 
                                        // Determine if this message is from the current user
                                        // If user is owner, then owner messages are from current user
                                        // If user is founder, then founder messages are from current user
                                        $isFromCurrentUser = ($is_owner && $chat['message_type'] === 'owner') || 
                                                           (!$is_owner && $chat['message_type'] === 'founder');
                                        
                                        // Apply 'owner' class to current user's messages (right side)
                                        // Apply 'founder' class to other person's messages (left side)
                                        $messageClass = $isFromCurrentUser ? 'owner' : 'founder';
                                    ?>
                                    <div class="message <?php echo $messageClass; ?>" data-message-id="<?php echo isset($chat['id']) ? $chat['id'] : ''; ?>" data-message-type="<?php echo $chat['message_type']; ?>">
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($chat['message'])); ?>
                                        </div>
                                        <div class="message-time">
                                            <?php echo date('M j, g:i a', strtotime($chat['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <h3>No Messages Yet</h3>
                                    <p>Be the first to write a message in this conversation!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="chat-input">
                            <form id="chat-form">
                                <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                                <input type="hidden" name="pet_id" value="<?php echo $pet_id; ?>">
                                <input type="hidden" name="founder_email" value="<?php echo htmlspecialchars($founder_email); ?>">
                                <input type="hidden" name="founder_name" value="<?php echo htmlspecialchars($founder_name); ?>">
                                <input type="hidden" name="is_owner" value="<?php echo $is_owner ? '1' : '0'; ?>">
                                <textarea name="message" id="message-input" placeholder="Type your message..." required></textarea>
                                <button type="submit" id="send-message-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h3>No Conversation Selected</h3>
                            <p>Select an existing conversation from the sidebar or start a new conversation from a pet's detail page.</p>
                        </div>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/main.js"></script>
    <script src="https://js.pusher.com/7.0/pusher.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        
        // Create a global tracker for received messages that's accessible to all functions
        const receivedMessageIds = new Set();
        
        // Initialize with current messages
        if (chatMessages) {
            document.querySelectorAll('.message').forEach(msg => {
                const msgId = msg.dataset.messageId;
                if (msgId) {
                    receivedMessageIds.add(msgId.toString());
                }
            });
        }
        
        // Auto-resize textarea as user types
        if (messageInput) {
            // Set initial height
            messageInput.style.height = 'auto';
            
            // Function to adjust height based on content
            const adjustHeight = () => {
                messageInput.style.height = 'auto';
                const newHeight = Math.min(messageInput.scrollHeight, 80);
                messageInput.style.height = newHeight + 'px';
            };
            
            // Apply when content changes
            messageInput.addEventListener('input', adjustHeight);
            
            // Handle Enter key for sending
            messageInput.addEventListener('keydown', function(e) {
                // If Enter is pressed without Shift key, send message
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (chatForm) {
                        // Trigger form submission
                        const submitEvent = new Event('submit', { cancelable: true });
                        chatForm.dispatchEvent(submitEvent);
                    }
                }
            });
        }
        
        // Function to handle form submission
        function handleFormSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            if (!form) return;
            
            const messageInput = form.querySelector('#message-input');
            if (!messageInput) return;
            
            const message = messageInput.value.trim();
            if (message === '') return;
            
            // Add local message immediately for better UX
            const isOwner = form.querySelector('input[name="is_owner"]')?.value === '1';
            const conversationId = form.querySelector('input[name="conversation_id"]')?.value;
            const tempId = 'temp-' + Date.now();
            
            const tempMessage = {
                id: tempId,
                message_type: isOwner ? 'owner' : 'founder',
                message: message,
                created_at: new Date().toISOString(),
                formatted_time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
            };
            
            // Add temporary message to UI before server response
            addLocalMessage(tempMessage);
            
            // Clear the input immediately
            messageInput.value = '';
            if (messageInput.style.height) {
                messageInput.style.height = 'auto';
            }
            
            // Focus back on input
            messageInput.focus();
            
            // Prepare form data
            const formData = new FormData(form);
            
            // Ensure message is included in the form data
            if (!formData.has('message') || formData.get('message').trim() === '') {
                formData.set('message', message);
            }
            
            // Send the message
            fetch('../messages/send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Message sent successfully', data);
                    // Find and update the temporary message with the real ID
                    const tempMsg = document.querySelector(`.message[data-message-id="${tempId}"]`);
                    if (tempMsg && data.data && data.data.id) {
                        tempMsg.dataset.messageId = data.data.id;
                        receivedMessageIds.add(data.data.id.toString());
                    }
                } else {
                    console.error('Error sending message:', data.message);
                    alert('Error sending message: ' + data.message);
                    
                    // Remove the temporary message that failed
                    const tempMsg = document.querySelector(`.message[data-message-id="${tempId}"]`);
                    if (tempMsg) {
                        tempMsg.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                // Try to get a more detailed error message
                if (error instanceof TypeError && error.message.includes('JSON')) {
                    alert('Error parsing server response. Please check server logs.');
                } else {
                    alert('Network error sending message. Please try again.');
                }
                
                // Remove the temporary message that failed
                const tempMsg = document.querySelector(`.message[data-message-id="${tempId}"]`);
                if (tempMsg) {
                    tempMsg.remove();
                }
            });
        }
        
        // Attach event listener to form submit
        if (chatForm) {
            chatForm.addEventListener('submit', handleFormSubmit);
        }
        
        // Function to verify and ensure chat input is present
        function verifyChatStructure() {
            const chatMain = document.getElementById('chat-main-container');
            if (!chatMain) return;
            
            const chatInput = chatMain.querySelector('.chat-input');
            const conversationId = document.querySelector('input[name="conversation_id"]')?.value;
            
            if (conversationId && !chatInput) {
                console.log("Chat input missing, restoring...");
                
                // Create chat input container
                const newChatInput = document.createElement('div');
                newChatInput.className = 'chat-input';
                
                // Get form values from hidden inputs or page data
                const petId = document.querySelector('input[name="pet_id"]')?.value || '';
                const founderEmail = document.querySelector('input[name="founder_email"]')?.value || '';
                const founderName = document.querySelector('input[name="founder_name"]')?.value || '';
                const isOwner = document.querySelector('input[name="is_owner"]')?.value || '0';
                
                // Create the form
                newChatInput.innerHTML = `
                    <form id="chat-form">
                        <input type="hidden" name="conversation_id" value="${conversationId}">
                        <input type="hidden" name="pet_id" value="${petId}">
                        <input type="hidden" name="founder_email" value="${founderEmail}">
                        <input type="hidden" name="founder_name" value="${founderName}">
                        <input type="hidden" name="is_owner" value="${isOwner}">
                        <textarea name="message" id="message-input" placeholder="Type your message..." required></textarea>
                        <button type="submit" id="send-message-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                `;
                
                // Add to the chat container
                chatMain.appendChild(newChatInput);
                
                // Set up event listeners
                const newForm = document.getElementById('chat-form');
                const newInput = document.getElementById('message-input');
                
                if (newForm) {
                    newForm.addEventListener('submit', handleFormSubmit);
                }
                
                if (newInput) {
                    // Set up auto-resize for the new input
                    newInput.addEventListener('input', () => {
                        newInput.style.height = 'auto';
                        const newHeight = Math.min(newInput.scrollHeight, 80);
                        newInput.style.height = newHeight + 'px';
                    });
                    
                    // Handle Enter key for the new input
                    newInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            if (newForm) {
                                const submitEvent = new Event('submit', { cancelable: true });
                                newForm.dispatchEvent(submitEvent);
                            }
                        }
                    });
                    
                    // Focus the new input
                    newInput.focus();
                }
            }
        }
        
        // Run structure verification every second to ensure chat input remains
        setInterval(verifyChatStructure, 1000);
        
        // Function to add a local message immediately
        function addLocalMessage(messageData) {
            if (!chatMessages) return;
            
            // Remove the empty state message if it exists
            const emptyState = chatMessages.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            // Determine message class (owner or founder)
            const isOwner = document.querySelector('input[name="is_owner"]')?.value === '1';
            const isFromCurrentUser = (isOwner && messageData.message_type === 'owner') || 
                                      (!isOwner && messageData.message_type === 'founder');
            const messageClass = isFromCurrentUser ? 'owner' : 'founder';
            
            // Create message element
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message');
            messageDiv.classList.add(messageClass);
            messageDiv.dataset.messageId = messageData.id;
            messageDiv.dataset.messageType = messageData.message_type;
            
            // Parse message text for line breaks
            const messageHtml = messageData.message.replace(/\n/g, '<br>');
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    ${messageHtml}
                </div>
                <div class="message-time">
                    ${messageData.formatted_time || 'Just now'}
                </div>
            `;
            
            // Add to chat
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Function to add a new message
        function addNewMessage(messageData) {
            // Check if we've already received this message
            if (receivedMessageIds.has(messageData.id.toString())) {
                console.log("Skipping duplicate message:", messageData.id);
                return false;
            }
            
            // Track that we've received this message
            receivedMessageIds.add(messageData.id.toString());
            
            // Format the message for display
            const formattedMessage = {
                id: messageData.id,
                message_type: messageData.type || messageData.message_type, // Handle both formats
                message: messageData.message,
                created_at: messageData.created_at,
                formatted_time: messageData.formatted_time || new Date(messageData.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
            };
            
            // Add to UI
            addLocalMessage(formattedMessage);
            return true;
        }

        // Set up Pusher
        try {
            const conversationId = document.querySelector('input[name="conversation_id"]')?.value;
            
            if (conversationId) {
                // Initialize Pusher
                const pusher = new Pusher('<?php echo PUSHER_KEY; ?>', {
                    cluster: '<?php echo PUSHER_CLUSTER; ?>',
                    encrypted: true
                });
                
                // Subscribe to the conversation channel
                const channel = pusher.subscribe('conversation-' + conversationId);
                
                // Bind to new message events
                channel.bind('new-message', function(data) {
                    console.log('New message received via Pusher:', data);
                    
                    if (data.id && receivedMessageIds.has(data.id.toString())) {
                        console.log('Message already displayed, skipping');
                        return;
                    }
                    
                    const added = addNewMessage(data);
                    
                    // Handle notifications
                    const isOwner = document.querySelector('input[name="is_owner"]')?.value === '1';
                    const messageType = data.type || data.message_type;
                    const isFromCurrentUser = (isOwner && messageType === 'owner') || 
                                            (!isOwner && messageType === 'founder');
                    
                    if (added) {
                        // Mark message as read if window is focused
                        if (document.visibilityState === 'visible') {
                            fetch('../messages/notification_service.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    conversation_id: conversationId,
                                    message_id: data.id,
                                    is_owner: isOwner
                                })
                            });
                        }
                        
                        // Show notification if message is from other user and window is not focused
                        if (!isFromCurrentUser && document.visibilityState !== 'visible') {
                            const senderName = data.sender_name || 
                                             (messageType === 'owner' ? 
                                              document.querySelector('h2')?.textContent : 'Founder');
                            showNotification(senderName, data.message);
                        }
                    }
                });

                // Add visibility change handler
                document.addEventListener('visibilitychange', function() {
                    if (document.visibilityState === 'visible') {
                        const unreadMessages = document.querySelectorAll('.message[data-is-read="false"]');
                        unreadMessages.forEach(msg => {
                            const messageId = msg.dataset.messageId;
                            const messageType = msg.dataset.messageType;
                            const isOwner = document.querySelector('input[name="is_owner"]')?.value === '1';
                            
                            // Only mark other user's messages as read
                            if ((isOwner && messageType === 'founder') || (!isOwner && messageType === 'owner')) {
                                fetch('../messages/notification_service.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        conversation_id: conversationId,
                                        message_id: messageId,
                                        is_owner: isOwner
                                    })
                                });
                                msg.dataset.isRead = "true";
                            }
                        });
                    }
                });

                // Initial message load - only needed if Pusher fails
                const loadInitialMessages = () => {
                    fetch(`../messages/get_messages.php?conversation_id=${conversationId}&is_owner=${document.querySelector('input[name="is_owner"]')?.value || 0}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.status === 'success') {
                                let addedAnyMessage = false;
                                result.data.forEach(message => {
                                    const added = addNewMessage(message);
                                    if (added) addedAnyMessage = true;
                                });
                                
                                if (addedAnyMessage) {
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                            } else {
                                console.error('Error loading messages:', result.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error loading messages:', error);
                        });
                };
                
                // Load initial messages
                loadInitialMessages();
                
                // Auto-refresh every 10 seconds as a fallback
                const messageRefreshInterval = setInterval(loadInitialMessages, 10000);
                
                // Clean up on page unload
                window.addEventListener('beforeunload', () => {
                    clearInterval(messageRefreshInterval);
                    pusher.unsubscribe('conversation-' + conversationId);
                });
            }
        } catch (error) {
            console.error('Error setting up Pusher:', error);
        }

        // Add notification functionality
        let notificationQueue = [];
        let notificationTimeout = null;

        function showNotification(senderName, message) {
            if (!("Notification" in window)) {
                console.log("Browser does not support notifications");
                return;
            }

            const notificationData = {
                title: `New message from ${senderName}`,
                options: {
                    body: message,
                    icon: "../../assets/images/logo.png",
                    tag: 'chat-notification',
                    requireInteraction: true
                }
            };

            // Queue the notification
            notificationQueue.push(notificationData);

            // Process queue if not already processing
            if (!notificationTimeout) {
                processNotificationQueue();
            }
        }

        function processNotificationQueue() {
            if (notificationQueue.length === 0) {
                notificationTimeout = null;
                return;
            }

            const notification = notificationQueue.shift();

            if (Notification.permission === "granted") {
                const notif = new Notification(notification.title, notification.options);
                
                notif.onclick = function() {
                    window.focus();
                    this.close();
                };

                // Process next notification after 1 second
                notificationTimeout = setTimeout(processNotificationQueue, 1000);
            } else if (Notification.permission !== "denied") {
                Notification.requestPermission().then(function (permission) {
                    if (permission === "granted") {
                        processNotificationQueue();
                    }
                });
            }
        }

        // Update the Pusher event binding with proper notification handling
        if (conversationId) {
            // ...existing Pusher initialization code...

            channel.bind('new-message', function(data) {
                console.log('New message received via Pusher:', data);
                
                if (data.id && receivedMessageIds.has(data.id.toString())) {
                    console.log('Message already displayed, skipping');
                    return;
                }
                
                const added = addNewMessage(data);
                
                // Handle notifications
                const isOwner = document.querySelector('input[name="is_owner"]')?.value === '1';
                const messageType = data.type || data.message_type;
                const isFromCurrentUser = (isOwner && messageType === 'owner') || 
                                        (!isOwner && messageType === 'founder');
                
                if (!isFromCurrentUser && document.visibilityState !== 'visible' && added) {
                    const senderName = data.sender_name || 
                                     (messageType === 'owner' ? 
                                      document.querySelector('h2')?.textContent : 'Founder');
                    showNotification(senderName, data.message);
                }
            });

            // Request notification permission when chat is opened
            if ("Notification" in window) {
                if (Notification.permission === "default") {
                    document.addEventListener('click', function requestNotification() {
                        Notification.requestPermission();
                        document.removeEventListener('click', requestNotification);
                    }, { once: true });
                }
            }
        }
    });
    </script>
</body>
</html>