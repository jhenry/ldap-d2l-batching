<?php include 'LDAPQuery.php'; ?>

<!DOCTYPE html>
<html>
  <head>
    <title>Welcome to Glitch!</title>
    <meta name="description" content="A cool thing made with Glitch">
    <link id="favicon" rel="icon" href="https://glitch.com/edit/favicon-app.ico" type="image/x-icon">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
    <header>
      <h1>
	LDAP/Directory to CSV
      </h1>
    </header>

<?php 

  if (isset($_POST['attributes'])) {
	$attributes = explode(",", $_POST['attributes']);
    $attributes = array_map( 'strtolower', $attributes);
}
  if (isset($_POST['custom_role']) && isset($_POST['custom_courses'])) {
	$custom_courses = explode(",", $_POST['custom_courses']);
	$custom_role = $_POST['custom_role'];
}
else {
$custom_courses = explode(",", "Build-Your-Course-Sample,D2L-Sample-Eng1101,D2L-Sample-Outlines-Course,Lessons_d2l_sb-Sample");
$custom_role = "Learner";
}
?>

<?php if (isset($_POST['netids'])): ?>
  <?php
    $people = getPeople($_POST['netids']);
    $id_postfix = $_POST['sandbox_id'];
    $title_postfix = $_POST['sandbox_name'];
  ?>
<?php else: ?>
  <?php
    $people = array();
    $id_postfix = "_SANDBOX_" . date("Ym");
    $title_postfix = date("my") . ": Sandbox Space";
  ?>
<?php endif; ?>
    <main>
      <p class="bold">Sandboxes and user batch files.</p>
    
      <form action="index.php" method="post">
        <br />
        <label>Custom enrollments:</label>
        <input type="text" name="custom_courses" value="<?php echo implode(",", $custom_courses); ?>">
        <input type="text" name="custom_role" value="<?php echo $custom_role; ?>">
        <br />
        <label>Sandbox naming:</label>
        <input type="text" name="sandbox_id" value="<?php echo $id_postfix; ?>">
        <input type="text" name="sandbox_name" value="<?php echo $title_postfix; ?>">
        <input type="hidden" name="attributes" value="uid,uvmEduUUID,givenName,uvmEduSurname,mail">
        <br />
        <label>NetIDs to get from directory:</label>
        <input type="text" name="netids" placeholder="Feed me a comma separated list of NetID's." value="<?php echo $_POST['netids'] ?? NULL ?>">
        <button type="submit">Go</button>  (Or, <a href="">reset the form</a>)
      </form>
<pre>
</pre>
      <section class="dreams">
<?php if (isset($_POST['netids'])): ?>

<?php if ($people): ?>


      <p style="bold">Complete! <a href="results.zip">Download results.zip</a></p>

      <h2>Users</h2>
      <p><a href="tmp/users.csv">Download users.csv</a></p>
  <textarea class="results" id="users"><?php echo printPeople($people, $attributes); ?></textarea>
      <h2>Sandbox Courses</h2>
      <p><a href="tmp/sandbox-courses.csv">Download sandbox-courses.csv</a></p>
  <textarea class="results" id="courses"><?php echo printCourses($people, $id_postfix, $title_postfix); ?></textarea>
      <h2>Sandbox Enrollments</h2>
      <p><a href="tmp/sandbox-enrollments.csv">Download sandbox-enrollments.csv</a></p>
  <textarea class="results" id="enrollments"><?php echo printSandboxEnrollments($people, $id_postfix); ?></textarea>
      <h2>Custom Enrollments</h2>
      <p><a href="tmp/custom-enrollments.csv">Download custom-enrollments.csv</a></p>
  <textarea class="results" id="custom-enrollments"><?php echo printEnrollments($people, $custom_courses, $custom_role); ?></textarea>

<?php packageResults(); ?>

<?php else: ?>

      <p class="bold">FATAL ERROR: One or more NetID's were not found in the directory.</p>

<?php endif; ?>
<?php endif; ?>

      </section>
    </main>

  </body>
</html>

<?php

function getPeople($usernames) {
    $people = array();
    $missingNetids = array();
    $ldapSearch = new LDAPQuery();
    $netids = explode(",", $usernames);
    foreach( $netids as $netid ) {
      $entry = $ldapSearch->directory_query("uid", $netid);
      if( !$entry ) {
        echo "<p class=\"bold\">ERROR: Failed to find netid: $netid.</p>";
        $missingNetids[] = $netid;
      }
      $person = array_values($ldapSearch->cleanUpEntry($entry));
      $people[] = $person[0];
    }
    
    if( count($missingNetids) > 0 ) { 
      return false; 
    }
    else {
      return $people;
    }

}

function printPeople($people, $attributes) {
  $lines = "";

  // fix columns for bulk create
  $userColsAdd = array("password" => "", "role_name" => "Instructor", "is_active" => "1");
  array_splice($attributes, -1, 0, array_keys($userColsAdd));
  array_unshift($attributes, "CREATE");

  //loop through users
  foreach( $people as $user ) {

    $row = array_intersect_key( $user, array_flip($attributes) );

    // fix columns for bulk create
    $row = $row + $userColsAdd + array("CREATE"=>"CREATE");

    foreach( $attributes as $attribute ) {
     $lines .= $row[$attribute] . ","; 
    }
      $lines =  rtrim($lines, ',') . "\n";
  }

  file_put_contents('tmp/users.csv', $lines);
  return $lines;
}

function printCourses($people, $id_postfix, $title_postfix) {
  $lines = "";

  //loop through users
  foreach( $people as $user ) {
    $netid = strtoupper($user['uid']);
    $courseId = $netid . $id_postfix;
    $courseTitle = $netid . $title_postfix;
    $lines .= "$courseId,$courseTitle,SB_SEM,ct_user_sandboxes_cb,User Sandboxes Container,dept_Brightspace_Training_d2l,,FALSE,TRUE\n";
  }

  file_put_contents('tmp/sandbox-courses.csv', $lines);
  return $lines;

}

function printSandboxEnrollments($people, $id_postfix) {
  $lines = "";

  //loop through users
  foreach( $people as $user ) {
    $netid = $user['uid'];
    $courseId = strtoupper($netid) . $id_postfix;
    $lines .= "ENROLL,$netid,,Instructor,$courseId\n";
    $lines .= "ENROLL,D2LTest.Demo,,Learner,$courseId\n";
    $lines .= "ENROLL,Demo.Instructor,,Learner,$courseId\n";
    $lines .= "ENROLL,Demo.Student,,Learner,$courseId\n";
  }

  file_put_contents('tmp/sandbox-enrollments.csv', $lines);
  return $lines;
}

function packageResults() {
  $zip = new ZipArchive();
  $ret = $zip->open('results.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
  if ($ret !== TRUE) {
    printf('Failed with code %d', $ret);
  } else {
    //$options = array('add_path' => 'tmp/', 'remove_all_path' => TRUE);
    //$zip->addGlob('*.{csv}', GLOB_BRACE, $options);
    $zip->addFile('tmp/users.csv');
    $zip->addFile('tmp/sandbox-courses.csv');
    $zip->addFile('tmp/sandbox-enrollments.csv');
    $zip->addFile('tmp/custom-enrollments.csv');
    $zip->close();
  }
}

//batch a list of users into a specific list of existing course offerings
function printEnrollments($people, $courses, $role="Instructor") {
  $lines = "";

  //loop through users
  foreach( $people as $user ) {
    $netid = $user['uid'];
    //loop through courses to add them to
    foreach( $courses as $courseId ) {
      $lines .= "ENROLL,$netid,,$role,$courseId\n";
    }
  }

  file_put_contents('tmp/custom-enrollments.csv', $lines);
  return $lines;
}
