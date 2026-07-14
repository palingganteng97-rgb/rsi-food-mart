<?php
include 'db.php';

// Menangkap otomatis nama ruangan dari scan QR Code
$room_otomatis = isset($_GET['room']) ? htmlspecialchars($_GET['room']) : '';

if (isset($_POST['submit'])) {
    $medical_record_number = mysqli_real_escape_string($conn, $_POST['medical_record_number']);
    $patient_name          = mysqli_real_escape_string($conn, $_POST['patient_name']);
    $phone                 = mysqli_real_escape_string($conn, $_POST['phone']);
    $room                  = mysqli_real_escape_string($conn, $_POST['room']);
    $bed                   = mysqli_real_escape_string($conn, $_POST['bed']);
    $class                 = mysqli_real_escape_string($conn, $_POST['class']);
    $doctor                = mysqli_real_escape_string($conn, $_POST['doctor']);
    
    // Waktu masuk session pasien
    $login_at              = date('Y-m-d H:i:s');
    // Expired otomatis 1 hari kemudian (bisa disesuaikan)
    $expired_at            = date('Y-m-d H:i:s', strtotime('+1 day')); 

    $query = "INSERT INTO patient_sessions (medical_record_number, patient_name, phone, room, bed, class, doctor, login_at, expired_at) 
              VALUES ('$medical_record_number', '$patient_name', '$phone', '$room', '$bed', '$class', '$doctor', '$login_at', '$expired_at')";

    if (mysqli_query($conn, $query)) {
        $patient_session_id = mysqli_insert_id($conn);

        // Simpan session pasien (pisah dari admin/login)
        $_SESSION['patient_session_id'] = (int)$patient_session_id;
        $_SESSION['patient_name'] = $patient_name;
        $_SESSION['medical_record_number'] = $medical_record_number;
        $_SESSION['room'] = $room;
        $_SESSION['bed'] = $bed;
        $_SESSION['class'] = $class;
        $_SESSION['doctor'] = $doctor;
        $_SESSION['login_at'] = $login_at;
        $_SESSION['expired_at'] = $expired_at;

        header("Location: home.php");
        exit;
    } else {
        echo "<script>alert('Gagal memproses data: " . mysqli_error($conn) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Sesi Pasien</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
</head>
<style>
    body {
        min-height: 100vh;
        background: #0f172a;
        background-image: url('uploads/bg_hospital.jpg');
        background-size: cover;
        background-position: center;
        position: relative;
        overflow-x: hidden;
    }
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.65);
        z-index: 0;
    }
    .glassmorphism {
        background: rgba(20,20,20,0.55) !important;
        border: 1px solid rgba(255,255,255,0.15) !important;
        box-shadow: 0 20px 60px rgba(0,0,0,0.45);
        color: #fff;
        z-index: 1;
    }
    .glassmorphism .form-label,
    .glassmorphism .card-header,
    .glassmorphism label,
    .glassmorphism h1, .glassmorphism h2, .glassmorphism h3, .glassmorphism h4, .glassmorphism h5, .glassmorphism h6 {
        color: #fff !important;
    }
    .glassmorphism input,
    .glassmorphism select {
        background: rgba(255,255,255,0.08) !important;
        border: 1px solid rgba(255,255,255,0.18) !important;
        color: #fff !important;
    }
    .glassmorphism input::placeholder { color: rgba(255,255,255,0.55) !important; }
    .header-rsi {
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
        background: linear-gradient(90deg, #16a34a, #22c55e) !important;
        border-bottom: 1px solid rgba(255,255,255,0.2) !important;
    }
    .fade-in {
        animation: fadeIn .6s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
<body>
<div class="d-flex align-items-center justify-content-center min-vh-100 px-3 py-4" style="position:relative; z-index:1;">
    <div class="card shadow-lg border-0 glassmorphism" style="max-width: 520px; width: 100%; animation: fadeIn .6s ease-out; backdrop-filter: blur(12px);">
        <div class="card-header bg-success text-white fw-bold text-center py-3 header-rsi">
            Form Masuk Sesi Pasien
        </div>
        <div class="card-body">
            <form action="patient_sessions.php" method="POST">
                
                <!-- INPUT OTOMATIS DARI QR CODE (READ-ONLY) -->
                <div class="mb-3 bg-warning bg-opacity-10 p-2 rounded border border-warning">
                    <label class="form-label fw-bold text-warning-emphasis">Lokasi Ruangan (Terdeteksi Otomatis)</label>
                    <input type="text" class="form-control fw-bold bg-white" name="room" value="<?= $room_otomatis; ?>" readonly required>
                </div>

                <div class="mb-3">
                    <label class="form-label">No. Rekam Medis (RM)</label>
                    <input type="text" class="form-control" name="medical_record_number" placeholder="Contoh: RM-10922" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Pasien</label>
                    <input type="text" class="form-control" name="patient_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Telepon/HP</label>
                    <input type="text" class="form-control" name="phone" required>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Nomor Bed/Kasur</label>
                        <input type="text" class="form-control" name="bed" placeholder="Contoh: B1" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Kelas Ruangan</label>
                        <input type="text" class="form-control" name="class" placeholder="Contoh: VIP / Kelas 1" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Dokter Penanggung Jawab</label>
                    <input type="text" class="form-control" name="doctor" required>
                </div>
                
                <button type="submit" name="submit" class="btn btn-success w-100 fw-bold py-2">
                    Mulai Sesi & Belanja
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
