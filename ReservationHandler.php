<?php
class ReservationHandler {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function validateReservation($room_id, $checkin, $checkout, $num_people, $exclude_reservation_id = null) {
        try {
            // First check if room exists and get its basic details
            $stmt = $this->conn->prepare("
                SELECT * FROM rooms WHERE id = ?
            ");
            $stmt->execute([$room_id]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$room) {
                return [
                    'valid' => false,
                    'message' => 'Invalid room selected'
                ];
            }

            // Check room status
            if ($room['status'] === 'maintenance') {
                return [
                    'valid' => false,
                    'message' => 'Room is currently under maintenance'
                ];
            }

            // Check capacity
            if ($num_people > $room['capacity']) {
                return [
                    'valid' => false,
                    'message' => "Room capacity exceeded. Maximum capacity is {$room['capacity']} people"
                ];
            }

            // Check if dates are valid
            $checkin_time = strtotime($checkin);
            $checkout_time = strtotime($checkout);
            $current_time = time();
            
            if ($checkin_time < strtotime('today midnight')) {
                return [
                    'valid' => false,
                    'message' => 'Check-in date must be today or in the future'
                ];
            }
            
            if ($checkout_time <= $checkin_time) {
                return [
                    'valid' => false,
                    'message' => 'Check-out date must be after check-in date'
                ];
            }

            // Check for conflicting reservations
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as conflicts
                FROM reservations 
                WHERE room_id = ? 
                AND id != ?
                AND status IN ('pending', 'confirmed')
                AND (
                    (checkin < ? AND checkout > ?) OR
                    (checkin < ? AND checkout > ?) OR
                    (checkin >= ? AND checkout <= ?)
                )
            ");
            
            $stmt->execute([
                $room_id,
                $exclude_reservation_id ?? 0,
                $checkout,
                $checkin,
                $checkout,
                $checkout,
                $checkin,
                $checkout
            ]);
            
            $conflicts = $stmt->fetch(PDO::FETCH_ASSOC)['conflicts'];
            
            if ($conflicts > 0) {
                return [
                    'valid' => false,
                    'message' => 'Room is already reserved for the selected dates'
                ];
            }
            
            // Calculate number of days and total price
            $num_days = max(1, ceil(($checkout_time - $checkin_time) / (60 * 60 * 24)));
            $total_price = $num_days * $room['price'];
            
            // All validations passed
            return [
                'valid' => true,
                'room' => $room,
                'num_days' => $num_days,
                'total_price' => $total_price
            ];
            
        } catch (Exception $e) {
            error_log("Reservation validation error: " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'An error occurred while validating the reservation'
            ];
        }
    }
    
    public function createReservation($user_id, $room_id, $checkin, $checkout, $num_people, $purpose = '') {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Validate the reservation first
            $validation = $this->validateReservation($room_id, $checkin, $checkout, $num_people);
            
            if (!$validation['valid']) {
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // Create the reservation
            $stmt = $this->conn->prepare("
                INSERT INTO reservations (
                    user_id, room_id, checkin, checkout, 
                    num_people, purpose, total_price, 
                    status, payment_status, created_at
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, ?,
                    'pending', 'pending', NOW()
                )
            ");
            
            $stmt->execute([
                $user_id,
                $room_id,
                $checkin,
                $checkout,
                $num_people,
                $purpose,
                $validation['total_price']
            ]);
            
            $reservation_id = $this->conn->lastInsertId();
            
            // Update room status to indicate pending reservation
            $stmt = $this->conn->prepare("
                UPDATE rooms 
                SET last_reserved = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$room_id]);
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'reservation_id' => $reservation_id,
                'details' => [
                    'id' => $reservation_id,
                    'room' => $validation['room'],
                    'checkin' => $checkin,
                    'checkout' => $checkout,
                    'num_days' => $validation['num_days'],
                    'num_people' => $num_people,
                    'total_price' => $validation['total_price']
                ]
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Reservation creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while creating the reservation'
            ];
        }
    }
    
    public function getReservation($reservation_id, $user_id = null) {
        try {
            $sql = "
                SELECT 
                    r.*, 
                    rm.name as room_name, 
                    rm.price as room_price, 
                    rm.capacity,
                    rm.status as room_status,
                    u.username, 
                    u.email
                FROM reservations r
                JOIN rooms rm ON r.room_id = rm.id
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ?
            ";
            $params = [$reservation_id];
            
            if ($user_id !== null) {
                $sql .= " AND r.user_id = ?";
                $params[] = $user_id;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reservation) {
                // Add some helper fields
                $reservation['is_past'] = strtotime($reservation['checkout']) < time();
                $reservation['is_active'] = !$reservation['is_past'] && $reservation['status'] === 'confirmed';
                $reservation['can_be_cancelled'] = $reservation['status'] === 'pending' || 
                    ($reservation['status'] === 'confirmed' && strtotime($reservation['checkin']) > time());
            }
            
            return $reservation;
            
        } catch (Exception $e) {
            error_log("Get reservation error: " . $e->getMessage());
            return false;
        }
    }
} 