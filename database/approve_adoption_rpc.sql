-- ============================================================
--  BantayPurrPaws — Atomic approval (MySQL stored procedure)
--  Optional: run in phpMyAdmin if you prefer DB-side logic.
--  The PHP app uses approveAdoption() in includes/db.php instead.
-- ============================================================

USE bantaypurrpaws;

DELIMITER //

CREATE PROCEDURE   approve_adoption(
    IN p_application_id INT,
    IN p_pet_id INT
)
BEGIN
    START TRANSACTION;

    UPDATE adoption_applications
       SET status = 'approved', updated_at = NOW()
     WHERE id = p_application_id;

    UPDATE pets
       SET status = 'adopted', updated_at = NOW()
     WHERE id = p_pet_id;

    UPDATE adoption_applications
       SET status = 'rejected', updated_at = NOW()
     WHERE pet_id = p_pet_id
       AND id <> p_application_id
       AND status = 'pending';

    COMMIT;
END //

DELIMITER ;
