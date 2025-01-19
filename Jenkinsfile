pipeline {
    agent { label 'docker-in-docker'}
    environment {
        CI_REGISTRY_URL = "gitlab.xel.nl:4567"
        KAMELEON_PIPELINE_TAG =  "${env.BUILD_NUMBER}"
    }
    stages {
        stage ('Test and report') {
            when { anyOf { branch 'master'; branch 'dev'; changeRequest() } }
            steps {
                slackSendMessage("#2aad72","""*Started:* - Job ${env.JOB_NAME} \n More info at: <${env.BUILD_URL} | *Here* >""")
                loginDockerGitlab()
                checkout scm
                // Build test image to run php/composer CLI commands in for building and testing
                sh """docker build -t zilch-wp-install-script:php-test -f scripts/docker/php-test.Dockerfile ."""
                // Unit test, lint and build
                sh """docker run --user appuser --name zilch-wp-install-script-${env.KAMELEON_PIPELINE_TAG} zilch-wp-install-script:php-test /bin/sh -c "cd /var/www/html && resources/composer install && resources/composer test && resources/composer lint-report && resources/composer build" """
                // Copy build executable (.phar) from container so it can be used by `ta.sh` script
                sh """docker cp zilch-wp-install-script-${env.KAMELEON_PIPELINE_TAG}:/var/www/html/zilch-wordpress-install-script.phar ./ """
                // Run ta
                sh """chmod +x tests/ta/ta.sh && cd tests/ta && ./ta.sh"""
            }
            post {
                always {
                    sh """ mkdir -p ./reports """
                    sh """ docker cp zilch-wp-install-script-${env.KAMELEON_PIPELINE_TAG}:/var/www/html/reports ./ """
                    sh """ ls -all ./reports"""

                    clover(cloverReportDir: '', cloverReportFileName: './reports/clover.xml',
                            // optional, default is: method=70, conditional=80, statement=80
                            healthyTarget: [methodCoverage: 70, conditionalCoverage: 80, statementCoverage: 80],
                            // optional, default is none
                            unhealthyTarget: [methodCoverage: 50, conditionalCoverage: 50, statementCoverage: 50],
                            // optional, default is none
                            failingTarget: [methodCoverage: 0, conditionalCoverage: 0, statementCoverage: 0]
                    )

                    publishHTML([allowMissing: false, alwaysLinkToLastBuild: false, keepAll: false, reportDir: './reports/coverage', reportFiles: '', reportName: 'PHP Code Coverage', reportTitles: ''])
                }
            }
        }
        stage('Create release tag') {
            when { anyOf { branch 'master' } }
            steps {
                checkout scm
                script {
                    getRepoURL()
                    getVersion()
                    getCommitEmail()
                    version = "${VERSION_NUMBER}"
                    // Push tag!
                    withCredentials([string(credentialsId: 'GITLAB_OAUTH_TOKEN', variable: 'OAUTH_API')]) {
                        sh """git remote set-url origin https://oauth2:${OAUTH_API}@${repositoryUrl}"""
                    }
                    // Create phar to be appended to the tag
                    sh """docker build -t zilch-wp-install-script:php-release -f scripts/docker/php-test.Dockerfile ."""
                    sh """docker run --name zilch-wp-install-script-${env.KAMELEON_PIPELINE_TAG}-phar zilch-wp-install-script:php-release /bin/sh -c "cd /var/www/html && resources/composer install && resources/composer build" """
                    sh """docker cp zilch-wp-install-script-${env.KAMELEON_PIPELINE_TAG}-phar:/var/www/html/zilch-wordpress-install-script.phar ./ """
                    sh """git config --global user.name \"Docker agent (using Jenkins)\" """
                    sh """git config --global user.email ${committerEmail} && echo \"Setting ${committerEmail} as Tag committer\"  """
                    sh """git add zilch-wordpress-install-script.phar"""
                    sh """git commit --no-verify -m \"Committing the new generated phar file [skip ci]\" """

                    // Create tag using current master branch code base + appended .phar file
                    sh """git tag -m \"Release tag version to ${version}\" -a ${version}  """
                    sh "git push origin ${version}"
                }
            }
        }
    }
    post {
        success{
            script {
                if (env.BRANCH_NAME == 'master') {
                    slackSendMessage("#2aad72","*${currentBuild.currentResult}:* - *Job* ${env.JOB_NAME} build ${env.BUILD_NUMBER} \n *Duration*: ${currentBuild.durationString.minus(' and counting')} \n Release tag version: *${VERSION_NUMBER}* \n More info at: <${env.BUILD_URL} | *Here* >")
                } else {
                    slackSendMessage("#2aad72","*${currentBuild.currentResult}:* - *Job* ${env.JOB_NAME} build ${env.BUILD_NUMBER} \n *Duration*: ${currentBuild.durationString.minus(' and counting')} \n More info at: <${env.BUILD_URL} | *Here* >")
                }
            }
        }
        unstable{
            slackSendMessage("#FCBA03","*${currentBuild.currentResult}:* - *Job* ${env.JOB_NAME} build ${env.BUILD_NUMBER} \n *Duration*: ${currentBuild.durationString.minus(' and counting')} \n More info at: <${env.BUILD_URL} | *Here* >")
        }
        failure {
            slackSendMessage("#FF0000","*${currentBuild.currentResult}:* - *Job* ${env.JOB_NAME} build ${env.BUILD_NUMBER} \n *Duration*: ${currentBuild.durationString.minus(' and counting')} \n More info at: <${env.BUILD_URL} | *Here* >")
        }
    }
}


def getCommitEmail(){
    committerEmail = sh (
            script: 'git --no-pager show -s --format=\'%ae\'',
            returnStdout: true
    ).trim()
}
def getRepoURL(){
    repositoryUrl = scm.userRemoteConfigs[0].url
    sh """echo repoUrl: '${repositoryUrl}'"""
    repositoryUrl = sh returnStdout: true, script: """(echo ${repositoryUrl} | sed 's|git@||' | sed 's|https://||')"""
}

def getVersion(){
    JSON_TEXT = readFile('composer.json').trim()
    JSON = readJSON text: JSON_TEXT
    VERSION = JSON.version
    VERSION_TRIM = VERSION.trim()
    VERSION_NUMBER = "${VERSION_TRIM}"
    return VERSION_NUMBER
}

def slackSendMessage(color, message){
    slackSend (
            channel: "#jenkinsbuilds",
            color: """$color""",
            message: """$message"""
    )
}

def loginDockerGitlab() {
    withCredentials([string(credentialsId: 'GITLAB_DOCKER_REGISTRY_USER_PW', variable: 'GITLAB_PASS')]) {
        sh """ echo '$GITLAB_PASS' |  docker login -u "kameleon_docker_registry" --password-stdin "${env.CI_REGISTRY_URL}" """
    }
}
