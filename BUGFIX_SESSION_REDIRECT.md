# Bug Fix: Session Redirect Issue

## Problem
Doctors and users were being redirected to the landing page when accessing certain pages (like `assessments.php`) even though they were properly logged in. Other pages like `dashboard.php` and `verify_habits.php` worked fine.

## Root Cause
The issue was caused by JavaScript code in `public/assets/js/app.js` that performs a client-side session validation check on page load.

### The Problematic Code Flow:
1. **Footer includes app.js**: `src/views/partials/footer.php` loads `app.js` on all pages
2. **app.js checks session**: On page load, `app.js` makes a fetch call to `/health-tracker/public/api.php?action=check_session`
3. **API call fails**: The fetch request returns a 404 error because the API endpoint path was incorrect or the endpoint didn't exist
4. **Automatic redirect**: When the fetch failed (catch block), it automatically redirected users to the landing page

```javascript
// OLD BUGGY CODE:
.catch(() => {
    // If check fails, assume session is invalid
    window.location.href = '/health-tracker/landing_page/index.php';  // ❌ BAD!
});
```

### Why Some Pages Worked and Others Didn't:
- The issue was timing-related and network-dependent
- Some pages loaded faster, allowing the JavaScript to execute before the user could interact
- Different pages might have had different JavaScript loading behavior

## Solution

### 1. Fixed `public/assets/js/app.js` - Session Check Logic
**Changed the error handling to NOT redirect on API failures:**

```javascript
// NEW FIXED CODE:
.then(response => {
    // Only process if we got a valid response
    if (!response.ok) {
        // API endpoint doesn't exist or errored - don't redirect
        console.warn('Session check endpoint not available, skipping validation');
        return null;
    }
    return response.json();
})
.then(data => {
    // Only redirect if we explicitly got a session invalid response
    if (data && !data.success && data.authenticated === false) {
        window.location.href = '/health-tracker/landing_page/index.php';
    }
})
.catch((error) => {
    // If check fails, don't redirect - the page already has server-side auth
    console.warn('Session check failed:', error);
});
```

**Key Changes:**
- Don't redirect if the API endpoint doesn't exist (404 error)
- Don't redirect on network errors (catch block)
- Only redirect if we explicitly receive a "not authenticated" response
- Rely on server-side authentication as the primary security check

### 2. Fixed API Path in `public/assets/js/auth.js`
**Changed from:**
```javascript
this.apiBase = '/api.php';  // ❌ Wrong path
```

**To:**
```javascript
this.apiBase = '/health-tracker/public/api.php';  // ✅ Correct path
```

### 3. Fixed API Path in `public/assets/js/app.js`
**Changed from:**
```javascript
this.apiBase = '/api.php';  // ❌ Wrong path
```

**To:**
```javascript
this.apiBase = '/health-tracker/public/api.php';  // ✅ Correct path
```

### 4. Fixed Doctor Header Auth Initialization
**Changed `doctor/includes/header.php` to use global $auth instead of creating a new instance:**

```php
// OLD CODE:
$auth = new Auth();  // ❌ Creates duplicate instance

// NEW CODE:
global $auth, $currentUser;  // ✅ Uses existing instance from bootstrap
```

### 5. Added Output Buffering
**Added `ob_start()` to `src/helpers/Bootstrap.php`** to prevent "headers already sent" errors.

## Files Modified

1. ✅ `public/assets/js/app.js` - Fixed session check logic and API path
2. ✅ `public/assets/js/auth.js` - Fixed API path
3. ✅ `doctor/includes/header.php` - Fixed Auth instance usage
4. ✅ `src/helpers/Bootstrap.php` - Added output buffering
5. ✅ `src/helpers/Auth.php` - Enhanced session checks (minor improvements)
6. ✅ `doctor/includes/bootstrap.php` - Enhanced error handling (minor improvements)

## Testing

### To Verify the Fix:
1. Log in as a doctor: `http://localhost/health-tracker/public/login.php`
2. Navigate to dashboard: `http://localhost/health-tracker/doctor/dashboard.php` ✅ Should work
3. Click on "Assessments": `http://localhost/health-tracker/doctor/assessments.php` ✅ Should work (FIXED!)
4. Click on other pages: All should work without unexpected redirects ✅

### Session Validation:
- Server-side authentication (PHP) is the primary security mechanism
- Client-side session check (JavaScript) is now a non-blocking enhancement
- If the JavaScript check fails due to network/API issues, it won't affect page access

## Key Takeaways

1. **Trust server-side auth first**: Never rely solely on client-side JavaScript for authentication
2. **Graceful degradation**: Client-side checks should fail gracefully without breaking functionality
3. **Proper error handling**: Don't assume all fetch failures mean authentication issues
4. **Consistent paths**: Use full paths for API endpoints to avoid 404 errors
5. **Output buffering**: Essential for preventing header-related errors in PHP

## Date Fixed
November 22, 2025

## Fixed By
AI Assistant (Senior Software Engineer approach)
