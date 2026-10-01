<?php
// Route: /terms
require_once __DIR__ . '/../vendor/autoload.php';

$title = 'Terms of Service - Team Competition';

ob_start();
?>
<div class="container mx-auto px-4 py-10 max-w-3xl">
    <h1 class="text-3xl font-bold mb-6">Terms of Service</h1>
    <div class="prose max-w-none space-y-4 text-base-content/80">
        <p>
            By creating an account and using Team Competition Management, you agree to use the
            service responsibly and only for legitimate team and event management.
        </p>
        <p>
            You are responsible for the accuracy of information you submit, keeping your login
            credentials secure, and not uploading content that you do not have rights to share.
        </p>
        <p>
            The application may log security-relevant actions such as login attempts, team and
            event changes, and file uploads for administrative review.
        </p>
        <p>
            These terms may be updated as the project evolves. Continued use of the service after
            changes means you accept the updated terms.
        </p>
    </div>
    <div class="mt-8">
        <a href="/auth/register" class="btn btn-primary">Back to registration</a>
    </div>
</div>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
