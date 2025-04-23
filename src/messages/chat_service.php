<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/pdo.php';

/**
 * Chat Service for handling message operations
 */
class ChatService {
    private $pdo;
    private $pusher;
    
    /**
     * Constructor
     * @param PDO $pdo PDO connection
     * @param Pusher $pusher Pusher instance
     */
    public function __construct($pdo, $pusher) {
        $this->pdo = $pdo;
        $this->pusher = $pusher;
    }
    
    /**
     * Get all conversations for a user
     * @param int $userId User ID
     * @param string $userType Type of user (owner or founder)
     * @return array Conversations
     */
    public function getConversations($userId, $userType = 'owner') {
        if ($userType === 'owner') {
            $stmt = $this->pdo->prepare("
                SELECT c.*, p.name AS pet_name, p.image_path AS pet_image 
                FROM conversations c
                JOIN pets p ON c.pet_id = p.id
                WHERE c.owner_id = ?
                ORDER BY c.last_message_time DESC
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT c.*, p.name AS pet_name, p.image_path AS pet_image 
                FROM conversations c
                JOIN pets p ON c.pet_id = p.id
                WHERE c.founder_email = ?
                ORDER BY c.last_message_time DESC
            ");
            $stmt->execute([$userId]); // For founders, userId is actually the email
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get conversation details
     * @param int $conversationId Conversation ID
     * @return array Conversation details
     */
    public function getConversation($conversationId) {
        $stmt = $this->pdo->prepare("
            SELECT c.*, p.name AS pet_name, p.image_path AS pet_image,
                  u.name AS owner_name, u.email AS owner_email
            FROM conversations c
            JOIN pets p ON c.pet_id = p.id
            JOIN users u ON c.owner_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$conversationId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get messages for a conversation
     * @param int $conversationId Conversation ID
     * @return array Messages
     */
    public function getMessages($conversationId) {
        // Fetch owner messages
        $ownerStmt = $this->pdo->prepare("
            SELECT om.*, 'owner' AS type, u.name AS sender_name
            FROM owner_messages om
            JOIN users u ON om.owner_id = u.id
            WHERE om.conversation_id = ?
        ");
        $ownerStmt->execute([$conversationId]);
        $ownerMessages = $ownerStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch founder messages
        $founderStmt = $this->pdo->prepare("
            SELECT fm.*, 'founder' AS type, fm.founder_name AS sender_name
            FROM founder_messages fm
            WHERE fm.conversation_id = ?
        ");
        $founderStmt->execute([$conversationId]);
        $founderMessages = $founderStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine and sort by timestamp
        $messages = array_merge($ownerMessages, $founderMessages);
        usort($messages, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        return $messages;
    }
    
    /**
     * Send a message
     * @param array $data Message data
     * @return array|bool The sent message or false on failure
     */
    public function sendMessage($data) {
        try {
            $this->pdo->beginTransaction();
            
            $isOwner = filter_var($data['is_owner'], FILTER_VALIDATE_BOOLEAN);
            $conversationId = $data['conversation_id'];
            $message = $data['message'];
            
            // Format message data for database
            if ($isOwner) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO owner_messages (conversation_id, pet_id, owner_id, message, is_read)
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $conversationId,
                    $data['pet_id'],
                    $data['owner_id'],
                    $message
                ]);
                $messageId = $this->pdo->lastInsertId();
                
                // Update unread count for founder
                $unreadStmt = $this->pdo->prepare("
                    UPDATE conversations 
                    SET founder_unread_count = founder_unread_count + 1,
                        last_message_time = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $unreadStmt->execute([$conversationId]);
                
                // Get sender info
                $senderStmt = $this->pdo->prepare("
                    SELECT name FROM users WHERE id = ?
                ");
                $senderStmt->execute([$data['owner_id']]);
                $sender = $senderStmt->fetch(PDO::FETCH_ASSOC);
                
                $messageData = [
                    'id' => $messageId,
                    'conversation_id' => $conversationId,
                    'message' => $message,
                    'sender_name' => $sender['name'],
                    'type' => 'owner',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO founder_messages (conversation_id, pet_id, founder_name, founder_email, message, is_read)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $conversationId,
                    $data['pet_id'],
                    $data['founder_name'],
                    $data['founder_email'],
                    $message
                ]);
                $messageId = $this->pdo->lastInsertId();
                
                // Update unread count for owner
                $unreadStmt = $this->pdo->prepare("
                    UPDATE conversations 
                    SET owner_unread_count = owner_unread_count + 1,
                        last_message_time = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $unreadStmt->execute([$conversationId]);
                
                $messageData = [
                    'id' => $messageId,
                    'conversation_id' => $conversationId,
                    'message' => $message,
                    'sender_name' => $data['founder_name'],
                    'type' => 'founder',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Trigger Pusher event
            $this->pusher->trigger(
                'conversation-' . $conversationId,
                'new-message',
                $messageData
            );
            
            $this->pdo->commit();
            return $messageData;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log('Error sending message: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark messages as read
     * @param int $conversationId Conversation ID
     * @param string $userType Type of user (owner or founder)
     * @return bool Success status
     */
    public function markAsRead($conversationId, $userType = 'owner') {
        try {
            if ($userType === 'owner') {
                // Mark founder messages as read
                $stmt = $this->pdo->prepare("
                    UPDATE founder_messages 
                    SET is_read = 1 
                    WHERE conversation_id = ? AND is_read = 0
                ");
                $stmt->execute([$conversationId]);
                
                // Reset owner unread count
                $unreadStmt = $this->pdo->prepare("
                    UPDATE conversations 
                    SET owner_unread_count = 0
                    WHERE id = ?
                ");
                $unreadStmt->execute([$conversationId]);
            } else {
                // Mark owner messages as read
                $stmt = $this->pdo->prepare("
                    UPDATE owner_messages 
                    SET is_read = 1 
                    WHERE conversation_id = ? AND is_read = 0
                ");
                $stmt->execute([$conversationId]);
                
                // Reset founder unread count
                $unreadStmt = $this->pdo->prepare("
                    UPDATE conversations 
                    SET founder_unread_count = 0
                    WHERE id = ?
                ");
                $unreadStmt->execute([$conversationId]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Error marking messages as read: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new conversation
     * @param array $data Conversation data
     * @return int|bool The new conversation ID or false on failure
     */
    public function createConversation($data) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO conversations (
                    pet_id, founder_name, founder_email, 
                    owner_id, owner_name, owner_email
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['pet_id'],
                $data['founder_name'],
                $data['founder_email'],
                $data['owner_id'],
                $data['owner_name'],
                $data['owner_email']
            ]);
            $conversationId = $this->pdo->lastInsertId();
            
            $this->pdo->commit();
            return $conversationId;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log('Error creating conversation: ' . $e->getMessage());
            return false;
        }
    }
}

// Create service instance if file is included
$chatService = new ChatService($pdo, $pusher); 