# Autodeploy PHP script

Create deploy_config.json in your git project.

Example deploy_config.json:

```json
    {
        "DEPLOY_USER":"user",
        "DEPLOY_SERVER":"localhost",
        "DEPLOY_DIR":"/var/www/any/",
        "REMOTE_GROUP":"apache",
        "REMOTE_PERM":"0777",
        "DELETE_FILES":false,
        "EXCLUDE":[".git/", ".idea/"],
        "BACKUP_DIR":false,
        "TIME_LIMIT":"30",
        "CLEAN_UP":true,
        "USE_COMPOSER":false,
        "COMPOSER_OPTIONS":"--no-dev",
        "VERSION_FILE":"",
        "EMAIL_ON_ERROR":"example@example.com"
    }
```

Set webhook url in Github/Gitlab (http://localhost/deploy.php?sat=SECRET_TOKEN)

Set permisson on DEPLOY_DIR to DEPLOY_USER

Run command git push and enjoy autodeploy ;)

p.s.: Other branch may be different configuration json file e.g.: `"DEPLOY_DIR":"/var/www/any_feature_branch/",`

_Inspired by [Simple PHP Git deploy script by markomarkovic](https://github.com/markomarkovic/simple-php-git-deploy/)_