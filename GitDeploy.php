<?php
/**
 * Author: Toth Sandor (toth.sandor91@gmail.com
 * Date: 2014.10.20.
 * @version 1.0
 * @link    https://github.com/Sanya1991/php-git-deploy-script/
 *
 * Forked from: https://github.com/markomarkovic/simple-php-git-deploy/
 * Original script by: Marko Marković
 */

class GitDeploy
{
    /**
     * Protect the script from unauthorized access by using a secret access token.
     * If it's not present in the access URL as a GET variable named `sat`
     * e.g. deploy.php?sat=Bett...s the script is not going to deploy.
     *
     * @var string
     */
    public $SECRET_ACCESS_TOKEN;

    /**
     * Configuration file in git project
     *
     * @var string
     */
    public $JSON_CONFIG_FILE='deploy-config.json';

    /**
     * If Git call webhook then send event (e.g.: push) information in json object format.
     *
     * @var string
     */
    public $GIT_HOOK;

    /**
     * The branch that's being deployed.
     * Must be present in the remote repository.
     *
     * @var string
     */
    public $BRANCH;

    /**
     * The address of the remote Git repository that contains the code that's being
     * deployed.
     * If the repository is private, you'll need to use the SSH address.
     *
     * @var string
     */
    public $REMOTE_REPOSITORY;

    /**
     * The location that the code is going to be deployed to.
     * Don't forget the trailing slash!
     *
     * @var string Full path including the trailing slash
     */
    public $TARGET_DIR;

    /**
     * Configuration options
     *
     *
     * The JSON string that is returned by edit() will normally indicate whether or not
     * the edits were performed successfully.
     *
     * Example:
     * {
     *  "DEPLOY_USER":"user", // Remote server user for rysnc (ssh) login
     *  "DEPLOY_SERVER":"localhost", // Remote server for rysnc
     *  "DEPLOY_DIR":"/var/www/any/", // Remote directory on remote server
     *  "REMOTE_GROUP":"apache", // Remote group on remote server
     *  "REMOTE_PERM":"0777", // Files and folders permissions
     *  "DELETE_FILES":false, // Delete files
     *  "EXCLUDE":[".git/", ".idea/"], // Skip this directory(s)
     *  "BACKUP_DIR":false, // Backup directory on local server
     *  "TIME_LIMIT":"30", // Script maximum execution  time
     *  "CLEAN_UP":true, // Delete the temp folder (on local) after copy
     *  "USE_COMPOSER":false, // Use composer (optional)
     *  "COMPOSER_OPTIONS":"--no-dev", // Composer option (optional)
     *  "VERSION_FILE":"", // Version number (optional)
     *  "EMAIL_ON_ERROR":"example@example.com" // In case of failure notification e-mail address
     * }
     *
     * Use: $JSON_CONFIG->DEPLOY_USER ( Object, not array )
     *
     * @var JSON object
     */
    public $JSON_CONFIG;

    /**
    * $JSON_CONFIG->DEPLOY_USER
    * $JSON_CONFIG->DEPLOY_SERVER
    * $JSON_CONFIG->DEPLOY_DIR
    * $JSON_CONFIG->REMOTE_GROUP
    * $JSON_CONFIG->REMOTE_PERM
    * $JSON_CONFIG->DELETE_FILES
    * $JSON_CONFIG->EXCLUDE
    * $JSON_CONFIG->BACKUP_DIR
    * $JSON_CONFIG->TIME_LIMIT
    * $JSON_CONFIG->CLEAN_UP
    * $JSON_CONFIG->USE_COMPOSER
    * $JSON_CONFIG->COMPOSER_OPTIONS
    * $JSON_CONFIG->VERSION_FILE
    * $JSON_CONFIG->EMAIL_ON_ERROR
    */

    function __construct($HTTP_RAW_POST_DATA)
    {
        $this->SECRET_ACCESS_TOKEN='SzuperTitkosDeployToken';
        $this->TMP_DIR = '/tmp/spgd-' . md5($this->REMOTE_REPOSITORY) . '/';

        if (isset($HTTP_RAW_POST_DATA)) {
            $this->GIT_HOOK = json_decode($HTTP_RAW_POST_DATA);
            $this->REMOTE_REPOSITORY = $this->GIT_HOOK->repository->homepage . '.git';
            $this->BRANCH = end(explode('/', $this->GIT_HOOK->ref));
        }

        if (!isset($_GET['sat']) || $_GET['sat'] !== $this->SECRET_ACCESS_TOKEN || $this->SECRET_ACCESS_TOKEN === 'BetterChangeMeNowOrSufferTheConsequences') {
            header('HTTP/1.0 403 Forbidden');
        }
        if (!isset($_GET['sat']) || $_GET['sat'] !== $this->SECRET_ACCESS_TOKEN) {
            die('<h2>ACCESS DENIED!</h2>');
        }
        if ($this->SECRET_ACCESS_TOKEN === 'BetterChangeMeNowOrSufferTheConsequences') {
            die("<h2>You're suffering the consequences!<br>Change the SECRET_ACCESS_TOKEN from it's default value!</h2>");
        }
        if ($this->REMOTE_REPOSITORY === '' or is_null($this->REMOTE_REPOSITORY)) {
            die('<h2>MISSING REMOTE REPOSITORY!!!</h2>');
        }
        if ($this->BRANCH === '' or is_null($this->BRANCH)) {
            die('<h2>MISSING BRANCH!!!</h2>');
        }
    }

    public function CheckEnvironment()
    {
        // Check if the required programs are available
        $requiredBinaries = array('git', 'rsync');
        foreach ($requiredBinaries as $command) {
            $path = trim(shell_exec('which ' . $command));
            if ($path == '') {
                die(sprintf('<div class="error"><b>%s</b> not available. It needs to be installed on the server for this script to work.</div>', $command));
            } else {
                $version = explode("\n", shell_exec($command . ' --version'));
                printf('<b>%s</b> : %s' . "\n"
                    , $path
                    , $version[0]
                );
            }
        }
    }

    public function GitPull()
    {
        if (!is_dir($this->TMP_DIR)) {
            // Clone the repository into the TMP_DIR
            $commands[] = sprintf(
                'git clone --depth=1 --branch %s %s %s'
                , $this->BRANCH
                , $this->REMOTE_REPOSITORY
                , $this->TMP_DIR
            );

        } else {

            // TMP_DIR exists and hopefully already contains the correct remote origin
            // so we'll fetch the changes and reset the contents.
            $commands[] = sprintf(
                'git --git-dir="%s.git" --work-tree="%s" fetch origin %s'
                , $this->TMP_DIR
                , $this->TMP_DIR
                , $this->BRANCH
            );
            $commands[] = sprintf(
                'git --git-dir="%s.git" --work-tree="%s" reset --hard FETCH_HEAD'
                , $this->TMP_DIR
                , $this->TMP_DIR
            );
        }
        return $commands;
    }

    public function SetConfigFromJson(){
        //Read json config file from repo
        $this->JSON_CONFIG=json_decode(file_get_contents($this->TMP_DIR.$this->JSON_CONFIG_FILE));

        // Set target dir
        $this->TARGET_DIR=$this->JSON_CONFIG->DEPLOY_USER.'@'.$this->JSON_CONFIG->DEPLOY_SERVER.':'.$this->JSON_CONFIG->DEPLOY_DIR;

        return $this->JSON_CONFIG;
    }

    public function AfterGitPull(){
        // Update the submodules
        $commands[] = sprintf(
            'git submodule update --init --recursive'
        );

        // Describe the deployed version
        if ($this->JSON_CONFIG->VERSION_FILE !== '') {
            $commands[] = sprintf(
                'git --git-dir="%s.git" --work-tree="%s" describe --always > %s'
                , $this->TMP_DIR
                , $this->TMP_DIR
                , $this->JSON_CONFIG->VERSION_FILE
            );
        }

        $requiredBinaries = array();
        if ($this->JSON_CONFIG->BACKUP_DIR && $this->JSON_CONFIG->BACKUP_DIR !== false) {
            $requiredBinaries[] = 'tar';
            if (!is_dir($this->JSON_CONFIG->BACKUP_DIR) || !is_writable($this->JSON_CONFIG->BACKUP_DIR)) {
                die(sprintf('<div class="error">BACKUP_DIR `%s` does not exists or is not writeable.</div>', $this->JSON_CONFIG->BACKUP_DIR));
            }
        }
        if ($this->JSON_CONFIG->USE_COMPOSER && $this->JSON_CONFIG->USE_COMPOSER === true) {
            $requiredBinaries[] = 'composer --no-ansi';
        }
        foreach ($requiredBinaries as $command) {
            $path = trim(shell_exec('which ' . $command));
            if ($path == '') {
                die(sprintf('<div class="error"><b>%s</b> not available. It needs to be installed on the server for this script to work.</div>', $command));
            } else {
                $version = explode("\n", shell_exec($command . ' --version'));
                printf('<b>%s</b> : %s' . "\n"
                    , $path
                    , $version[0]
                );
            }
        }

        // Backup the TARGET_DIR
        // without the BACKUP_DIR for the case when it's inside the TARGET_DIR
        if ($this->JSON_CONFIG->BACKUP_DIR !== false) {
            $commands[] = sprintf(
                "tar --exclude='%s*' -czf %s/%s-%s-%s.tar.gz %s*"
                , $this->JSON_CONFIG->BACKUP_DIR
                , $this->JSON_CONFIG->BACKUP_DIR
                , basename($this->TARGET_DIR)
                , md5($this->TARGET_DIR)
                , date('YmdHis')
                , $this->TARGET_DIR // We're backing up this directory into BACKUP_DIR
            );
        }

        // Invoke composer
        if ($this->JSON_CONFIG->USE_COMPOSER === true) {
            $commands[] = sprintf(
                'composer --no-ansi --no-interaction --no-progress --working-dir=%s install %s'
                , $this->TMP_DIR
                , ($this->JSON_CONFIG->COMPOSER_OPTIONS) ? $this->JSON_CONFIG->COMPOSER_OPTIONS : ''
            );
        }

        return $commands;
    }

    public function Deploy(){

        // Compile exclude parameters
        $exclude = '';
        foreach ($this->JSON_CONFIG->EXCLUDE as $exc) {
            $exclude .= ' --exclude=' . $exc;
        }

        // Create parrent directorys, Change permissons and Deployment
        $commands[] = sprintf(
            'rsync --rsync-path="mkdir -m %s -p %s && rsync" --chmod=ugo=rwx -rltogDzvO %s %s %s %s'
            , $this->JSON_CONFIG->REMOTE_PERM
            , $this->JSON_CONFIG->DEPLOY_DIR
            , $exclude
            , $this->TMP_DIR
            , $this->TARGET_DIR
            , ($this->JSON_CONFIG->DELETE_FILES) ? '--delete-after' : ''
        );

        // After Deployment change folders and files group and permissions
        $commands[] = sprintf(
            'ssh %s@%s "chgrp -R %s %s && chmod -R %s %s"'
            , $this->JSON_CONFIG->DEPLOY_USER
            , $this->JSON_CONFIG->DEPLOY_SERVER
            , $this->JSON_CONFIG->REMOTE_GROUP
            , $this->JSON_CONFIG->DEPLOY_DIR
            , $this->JSON_CONFIG->REMOTE_PERM
            , $this->JSON_CONFIG->DEPLOY_DIR
        );

        // Remove the TMP_DIR (depends on CLEAN_UP)
        if ($this->JSON_CONFIG->CLEAN_UP) {
            $commands['cleanup'] = sprintf(
                'rm -rf %s'
                , $this->TMP_DIR
            );
        }
        return $commands;
    }

    public function RunCommands($commands){
        $output = '';

        foreach ($commands as $command) {
            set_time_limit($this->JSON_CONFIG->TIME_LIMIT); // Reset the time limit for each command
            if (file_exists($this->TMP_DIR) && is_dir($this->TMP_DIR)) {
                chdir($this->TMP_DIR); // Ensure that we're in the right directory
            }
            $tmp = array();
            exec($command . ' 2>&1', $tmp, $return_code); // Execute the command
            // Output the result
            printf('
                    <span class="prompt">$</span> <span class="command">%s</span>
                    <div class="output">%s</div>
                '
                , htmlentities(trim($command))
                , htmlentities(trim(implode("\n", $tmp)))
            );
            $output .= ob_get_contents();
            ob_flush(); // Try to output everything as it happens

            // Error handling and cleanup
            if ($return_code !== 0) {
                printf('<div class="error">
                            Error encountered!
                            Stopping the script to prevent possible data loss.
                            CHECK THE DATA IN YOUR TARGET DIR!
                        </div>'
                );

                if ($this->JSON_CONFIG->CLEAN_UP) {
                    $tmp = shell_exec($commands['cleanup']);
                    printf('Cleaning up temporary files ...
                        <span class="prompt">$</span> <span class="command">%s</span>
                        <div class="output">%s</div>'
                        , htmlentities(trim($commands['cleanup']))
                        , htmlentities(trim($tmp))
                    );
                }

                $error = sprintf(
                    'Deployment error on %s using %s!'
                    , $_SERVER['HTTP_HOST']
                    , __FILE__
                );

                error_log($error);

                if ($this->JSON_CONFIG->EMAIL_ON_ERROR) {
                    $output .= ob_get_contents();
                    $headers = array();
                    $headers[] = sprintf('From: Simple PHP Git deploy script <simple-php-git-deploy@%s>', $_SERVER['HTTP_HOST']);
                    $headers[] = sprintf('X-Mailer: PHP/%s', phpversion());
                    mail($this->JSON_CONFIG->EMAIL_ON_ERROR, $error, strip_tags(trim($output)), implode("\r\n", $headers));
                }
                break;
            }
        }
    }
}