<?php
// Route: /auth/forgot-password
$title = 'Forgot Password - Team Competition';

ob_start();
?>
<div class="min-h-[80vh] flex items-center justify-center">
    <div class="card bg-base-100 shadow-xl w-full max-w-md">
        <div class="card-body">
            <h2 class="card-title text-2xl font-bold justify-center mb-4">ลืมรหัสผ่าน</h2>
            <p class="text-center text-sm text-base-content/70 mb-4">
                กรอกอีเมลของคุณเพื่อรับลิงก์รีเซ็ตรหัสผ่าน
            </p>

            <form method="POST" class="space-y-4">
                <?php
                $inputName = 'email';
                $inputLabel = 'อีเมล';
                $inputType = 'email';
                $inputPlaceholder = 'your@email.com';
                $inputRequired = true;
                include __DIR__ . '/../../templates/components/input.php';
                ?>

                <?php
                $btnText = 'ส่งลิงก์รีเซ็ตรหัสผ่าน';
                $btnClass = 'btn-primary w-full';
                include __DIR__ . '/../../templates/components/button.php';
                ?>
            </form>

            <div class="text-center mt-4">
                <p class="text-sm">
                    <a href="/auth/login" class="link link-primary">← กลับสู่หน้าเข้าสู่ระบบ</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
