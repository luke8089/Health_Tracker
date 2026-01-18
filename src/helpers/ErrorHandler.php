<?php
/**
 * Global Error Handler
 * Handles exceptions and errors gracefully
 */

class ErrorHandler {
    private static $logFile;
    
    public static function init($logFile = null) {
        self::$logFile = $logFile ?? __DIR__ . '/../../logs/error.log';
        
        // Ensure log directory exists
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set error and exception handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    public static function handleError($errno, $errstr, $errfile, $errline) {
        // Don't handle suppressed errors (@)
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $errorType = self::getErrorType($errno);
        $message = "[$errorType] $errstr in $errfile:$errline";
        
        self::logError($message);
        
        // For fatal errors, show error page
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            self::showErrorPage($message);
        }
        
        return true;
    }
    
    public static function handleException($exception) {
        $message = sprintf(
            "[EXCEPTION] %s: %s in %s:%d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        self::logError($message);
        self::showErrorPage($exception->getMessage());
    }
    
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $message = sprintf(
                "[FATAL] %s in %s:%d",
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            self::logError($message);
            self::showErrorPage('A fatal error occurred. Please try again later.');
        }
    }
    
    private static function getErrorType($errno) {
        $types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];
        
        return $types[$errno] ?? 'UNKNOWN';
    }
    
    private static function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        error_log($logMessage, 3, self::$logFile);
    }
    
    private static function showErrorPage($message) {
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'An error occurred. Please try again.'
            ]);
            exit;
        }
        
        // Show HTML error page
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error - Health Tracker</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-50">
            <div class="min-h-screen flex items-center justify-center px-4">
                <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center">
                    <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Oops! Something went wrong</h1>
                    <p class="text-gray-600 mb-6">We encountered an error while processing your request.</p>
                    <?php if (isset($_ENV['ENVIRONMENT']) && $_ENV['ENVIRONMENT'] === 'development'): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-left">
                            <p class="text-sm text-red-800 font-mono break-words"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="flex gap-3">
                        <button onclick="history.back()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            Go Back
                        </button>
                        <a href="/health-tracker/public/dashboard.php" class="flex-1 px-4 py-2 bg-gradient-to-r from-primary to-accent text-white rounded-lg hover:shadow-lg transition-all">
                            Go Home
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Initialize error handler
ErrorHandler::init();
