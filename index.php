<?php require_once 'config/db_config.php'; ?>
<?php include 'includes/header.php'; ?>

<div class="form-box">
    <h2>VISITOR CHECK-IN</h2>
    <form action="process_checkin.php" method="POST">
        <label>Jina lako Kamili</label>
        <input type="text" name="full_name" required pattern="[A-Za-z]+([ '\\.-][A-Za-z]+)*" title="Jina litumie herufi tu bila namba" placeholder="Mf. Juma Hamisi">

        <label>Namba yako ya Simu</label>
        <input type="text" name="visitor_phone" required maxlength="10" pattern="[0-9]+" inputmode="numeric" placeholder="Mf. 0712345678">

        <label>Unamtembelea Nani?</label>
        <select name="host_id" required>
            <option value="">-- Mchague Mwenyeji --</option>
            <?php
            $result = $conn->query("SELECT id, name FROM hosts");
            while($row = $result->fetch_assoc()) {
                echo "<option value='".$row['id']."'>".$row['name']."</option>";
            }
            ?>
        </select>

        <label>Sababu ya Ziara</label>
        <textarea name="purpose" rows="3" placeholder="Mf. Kikao cha Kiofisi"></textarea>

        <label>Visitor came with vehicle?</label>
        <select id="has_motor" name="has_motor">
            <option value="No">No</option>
            <option value="Yes">Yes</option>
        </select>

        <div id="motor_fields" style="display:none; margin-top:10px;">
            <label>Vehicle Type</label>
            <select name="motor_type">
                <option value="">-- Chagua --</option>
                <option value="Gari">Gari</option>
                <option value="Pikipiki">Pikipiki</option>
                <option value="Bajaj">Bajaj</option>
            </select>

            <label>Plate Number</label>
            <input type="text" name="plate_number" placeholder="Mfano: T123ABC">

            <label>Model / Brand</label>
            <input type="text" name="model_name" placeholder="e.g. Toyota" />
        </div>

        <button type="submit">Sajili na Tuma Taarifa</button>
    </form>
</div>

</body>
</html>