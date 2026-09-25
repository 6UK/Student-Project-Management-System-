<?php
// LINE 1: Imports the authentication restriction module to verify active session keys before processing files
require_once('../includes/auth_check.php');

// LINE 2: Gatekeeper Rule: Grants access strictly to users carrying administrative roles ('coordinator' or 'hod')
checkRole(['coordinator', 'hod']); 

// LINE 3: Pulls in the core configuration settings to open communication tunnels with the system database
require_once('../config/db_connect.php');

// LINE 4: Verification Check: Validates that both the project key ('pid') and student key ('sid') are active inside the GET array parameters
if (!isset($_GET['pid']) || !isset($_GET['sid'])) {
    // LINE 5: Hard Termination: Aborts execution immediately with a red alert block if required system hooks are missing
    die("<div style='font-family:sans-serif; padding:20px; color:#c0392b;'><h3>❌ Error: Missing parameters.</h3></div>");
}

// LINE 6: Type Safety Casting: Forces incoming routing variables to rigid base-10 integer formats to prevent query structure breaks
$project_id = intval($_GET['pid']);

// LINE 7: Type Safety Casting: Neutralizes cross-site script mapping by forcing the student identifier strictly into an integer scope
$student_id = intval($_GET['sid']);

// =========================================================================
// LINE 8: --- 1. FETCH TRANSCRIPT PATH ---
// =========================================================================

// LINE 9: Prepares a safe parameterized blueprint statement to look up the transcript tracking string inside student profile indexes
$file_stmt = $conn->prepare("SELECT transcript_path FROM student WHERE student_id = ?");

// LINE 10: Plugs the parsed student unique integer identification key directly into the query template placeholder register
$file_stmt->bind_param("i", $student_id);

// LINE 11: Launches the safe compiled lookup statement query against the active structural data tables
$file_stmt->execute();

// LINE 12: Collects the raw driver result map arrays and immediately splits the records out into a local array variable ($file_res)
$file_res = $file_stmt->get_result()->fetch_assoc();

// LINE 13: Memory Management: Closes the local database statement handler down to release allocated processing cache space
$file_stmt->close();

// LINE 14: Validation Guard: Tests if the database record failed to resolve entirely or holds an empty string parameter loop
if (!$file_res || empty($file_res['transcript_path'])) {
    // LINE 15: Structural Halt: Blocks system navigation workflow because no reference paths are currently logged under the user key
    die("<h3 style='font-family:sans-serif; color:#c0392b; padding:20px;'>❌ Error: Transcript path not found.</h3>");
}

// LINE 16: Normalization Layer: Swaps out Windows backslashes with standard Linux forward slashes to build cross-platform file paths
$file_path = str_replace('\\', '/', $file_res['transcript_path']); 

// LINE 17: Disk Sanity Check: Verifies if the normalized path link successfully locates the true binary asset profile out on server storage
if (!file_exists($file_path)) {
    
    // LINE 18: Fallback Router: Re-aligns root directory references if files are run from inside deeply nested application sub-folders
    $fallback = "../" . ltrim($file_path, './');
    
    // LINE 19: Nested Fallback Validation: Re-evaluates if the updated tracking string successfully resolves the asset out on disk
    if (file_exists($fallback)) { 
        
        // LINE 20: Recovery Step: Explicitly updates the system file path variable to use the working relative location link
        $file_path = $fallback; 
        
    // LINE 21: Final Fault Block: Triggers if both the database link path and the path alignment lookups fail completely
    } else { 
        
        // LINE 22: Hard Termination: Safety cutoff preventing subsequent binary processing crashes from firing off due to empty files
        die("<h3 style='font-family:sans-serif; color:#c0392b; padding:20px;'>❌ Error: PDF file not found on disk.</h3>"); 
    }
}

// =========================================================================
// LINE 23: --- 2. STREAM DIRECTLY TO BROWSER TAB CLEANLY ---
// =========================================================================

// LINE 24: Buffer Clearance: Audits if any active server output buffer stacks are holding data strings in application execution memory
if (ob_get_level()) { 
    
    // LINE 25: Purge Action: Deletes and completely closes running output buffers to stop layout whitespaces from corrupting PDF code streams
    ob_end_clean(); 
}

// LINE 26: Header Declaration 1: Directs the incoming client browser to treat the incoming data packet stream explicitly as a standard PDF document
header('Content-Type: application/pdf');

// LINE 27: Header Declaration 2: Configures file disposition parameters to display natively 'inline' inside tabs instead of forcing download downloads
header('Content-Disposition: inline; filename="Transcript_' . $student_id . '.pdf"');

// LINE 28: Header Declaration 3: Declares the precise total file payload weight metric via filesize() to help browsers trace transfer speeds
header('Content-Length: ' . filesize($file_path));

// LINE 29: Stream Dispatcher: Directly reads the targeted file asset directly out from the disk space storage loop and writes it to the output channel
readfile($file_path);

// LINE 30: System Terminal: Safely closes out active processing cycles to prevent supplementary background data scripts from echoing onto the file trail
exit;
?>
