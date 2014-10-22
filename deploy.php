<?php include 'GitDeploy.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>PHP Deploy script</title>
    <style>
        body {
            padding: 0 1em;
            background: #222;
            color: #fff;
        }
        h2, .error {
            color: #c33;
        }
        .prompt {
            color: #6be234;
        }
        .command {
            color: #729fcf;
        }
        .output {
            color: #999;
        }
    </style>
</head>
<body>
<pre>
<?php $deploy=new GitDeploy($HTTP_RAW_POST_DATA); ?>
Running as <b><?php echo trim(shell_exec('whoami')); ?></b>.
Checking the environment ...
<?php $deploy->CheckEnvironment();?>
Environment OK.
Start deploy.
<?php
$deploy->RunCommands($deploy->GitPull());
$deploy->SetConfigFromJson();
$deploy->RunCommands($deploy->AfterGitPull());
$deploy->RunCommands($deploy->Deploy());
?>
Deploy done.
</pre>
</body>
</html>