<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class ChatFunctions {
    private $conn;

    public function __construct() {
        // Try different possible paths for database configuration
        $possible_paths = [
            '../config/database.php',
            'config/database.php',
            '../../config/database.php'
        ];
        
        $database_loaded = false;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $database_loaded = true;
                break;
            }
        }
        
        if (!$database_loaded) {
            throw new Exception("Database configuration file not found");
        }
        
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function updateOnlineStatus($emp_id, $is_online = 1) {
        $query = "INSERT INTO user_online_status (emp_id, is_online, last_seen) 
                  VALUES (?, ?, CURRENT_TIMESTAMP) 
                  ON DUPLICATE KEY UPDATE is_online = ?, last_seen = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $emp_id, $is_online, $is_online);
        return $stmt->execute();
    }

    public function getOnlineUsers($current_user_id) {
        // Reduce timeout to 1 minute for more accurate status
        $timeout_query = "UPDATE user_online_status SET is_online = 0 
                        WHERE last_seen < DATE_SUB(NOW(), INTERVAL 1 MINUTE) 
                        AND is_online = 1";
        $this->conn->query($timeout_query);
        
        $query = "SELECT e.emp_id, e.first_name, e.last_name, e.picture, 
                        uos.is_online, uos.last_seen
                FROM employee e
                LEFT JOIN user_online_status uos ON e.emp_id = uos.emp_id
                WHERE e.emp_id != ? AND e.employment_status_id = 1
                ORDER BY uos.is_online DESC, uos.last_seen DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }

    // Get private room - updated version
    public function getPrivateRoom($user1_id, $user2_id) {
        // Ensure consistent ordering to avoid duplicate rooms
        $min_id = min($user1_id, $user2_id);
        $max_id = max($user1_id, $user2_id);
        
        // Check if room already exists
        $query = "SELECT cr.room_id 
                FROM chat_rooms cr
                INNER JOIN chat_room_participants crp1 ON cr.room_id = crp1.room_id
                INNER JOIN chat_room_participants crp2 ON cr.room_id = crp2.room_id
                WHERE cr.room_type = 'private' 
                AND crp1.emp_id = ? AND crp2.emp_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $min_id, $max_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $room = $result->fetch_assoc();
            return $room['room_id'];
        }
        
        // Create new room
        $room_name = "Private Chat";
        $query = "INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES (?, 'private', ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $room_name, $user1_id);
        
        if ($stmt->execute()) {
            $room_id = $this->conn->insert_id;
            
            // Add participants
            $this->addRoomParticipant($room_id, $user1_id);
            $this->addRoomParticipant($room_id, $user2_id);
            
            return $room_id;
        }
        
        return false;
    }

    private function addRoomParticipant($room_id, $emp_id) {
        $query = "INSERT INTO chat_room_participants (room_id, emp_id) VALUES (?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $room_id, $emp_id);
        return $stmt->execute();
    }

    // Send message
    public function sendMessage($room_id, $sender_id, $message) {
        $query = "INSERT INTO chat_messages (room_id, sender_id, message, message_type) VALUES (?, ?, ?, 'text')";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iis", $room_id, $sender_id, $message);
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        return false;
    }

    // Get messages for a room
    public function getMessages($room_id, $last_message_id = 0) {
        $query = "SELECT cm.*, e.first_name, e.last_name, e.picture 
                  FROM chat_messages cm
                  JOIN employee e ON cm.sender_id = e.emp_id
                  WHERE cm.room_id = ? AND cm.message_id > ?
                  ORDER BY cm.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $room_id, $last_message_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        return $messages;
    }

    // Mark messages as read
    public function markMessagesAsRead($room_id, $user_id) {
        $query = "UPDATE chat_messages SET is_read = 1 
                  WHERE room_id = ? AND sender_id != ? AND is_read = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $room_id, $user_id);
        return $stmt->execute();
    }

    // Get unread message count
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as unread_count
                FROM chat_messages cm
                JOIN chat_room_participants crp ON cm.room_id = crp.room_id
                WHERE crp.emp_id = ? AND cm.sender_id != ? AND cm.is_read = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['unread_count'];
    }
    // Get unread counts per user
    public function getUnreadCounts($user_id) {
        $total_unread = 0;
        $user_unread_counts = [];
        
        // Get all private rooms where current user is a participant
        $rooms_query = "SELECT cr.room_id 
                    FROM chat_rooms cr 
                    JOIN chat_room_participants crp ON cr.room_id = crp.room_id 
                    WHERE crp.emp_id = ? AND cr.room_type = 'private'";
        
        $stmt = $this->conn->prepare($rooms_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $rooms_result = $stmt->get_result();
        $rooms = [];
        while ($row = $rooms_result->fetch_assoc()) {
            $rooms[] = $row;
        }
        
        foreach ($rooms as $room) {
            // Get the other participant in this private room
            $participant_query = "SELECT crp.emp_id 
                                FROM chat_room_participants crp 
                                WHERE crp.room_id = ? AND crp.emp_id != ?";
            
            $stmt = $this->conn->prepare($participant_query);
            $stmt->bind_param("ii", $room['room_id'], $user_id);
            $stmt->execute();
            $participant_result = $stmt->get_result();
            
            if ($participant_result->num_rows > 0) {
                $participant = $participant_result->fetch_assoc();
                $other_user_id = $participant['emp_id'];
                
                // Count unread messages from this user
                $unread_query = "SELECT COUNT(*) as unread_count 
                            FROM chat_messages 
                            WHERE room_id = ? 
                            AND sender_id = ? 
                            AND is_read = 0";
                
                $stmt = $this->conn->prepare($unread_query);
                $stmt->bind_param("ii", $room['room_id'], $other_user_id);
                $stmt->execute();
                $unread_result = $stmt->get_result();
                $unread_data = $unread_result->fetch_assoc();
                $unread_count = $unread_data['unread_count'];
                
                if ($unread_count > 0) {
                    $user_unread_counts[$other_user_id] = $unread_count;
                    $total_unread += $unread_count;
                }
            }
        }
        
        return [
            'total_unread' => $total_unread,
            'user_unread_counts' => $user_unread_counts
        ];
    }
    // Alternative simpler method to get unread counts
    public function getUnreadCountsSimple($user_id) {
        $query = "SELECT 
                    cm.sender_id,
                    COUNT(*) as unread_count,
                    e.first_name,
                    e.last_name
                FROM chat_messages cm
                JOIN chat_room_participants crp ON cm.room_id = crp.room_id
                JOIN employee e ON cm.sender_id = e.emp_id
                WHERE crp.emp_id = ? 
                    AND cm.sender_id != ? 
                    AND cm.is_read = 0
                GROUP BY cm.sender_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $total_unread = 0;
        $user_unread_counts = [];
        
        while ($row = $result->fetch_assoc()) {
            $user_unread_counts[$row['sender_id']] = $row['unread_count'];
            $total_unread += $row['unread_count'];
        }
        
        return [
            'total_unread' => $total_unread,
            'user_unread_counts' => $user_unread_counts
        ];
    }
}
?>