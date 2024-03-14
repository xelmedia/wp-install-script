pipeline {
    agent { label 'docker-in-docker' }
    stages {
        stage('TA') {
            when { anyOf { branch 'master'; branch 'dev'} }
            steps {
                script {
                    checkout scm
                    slackSend (
                            channel: "#jenkinsbuilds",
                            color: '#4287f5',
                            message: """*Started:* - Job ${env.JOB_NAME} build ${env.BUILD_NUMBER} \n More info at: <${env.BUILD_URL} | *Here* >"""
                    )
                    sh """chmod +x tests/ta/ta.sh && cd tests/ta && ./ta.sh"""
                }
            }
        }
        stage('Create release tag') {
            when { anyOf { branch 'dev' } }
            steps {
                checkout scm
                script {
                    getRepoURL()
                    getVersion()
                    getCommitEmail()
                    version = "${VERSION_NUMBER}-rc "
                    echo "${version}"
                    // Push tag!
                    withCredentials([string(credentialsId: 'GITLAB_OAUTH_TOKEN', variable: 'OAUTH_API')]) {
                        sh """git remote set-url origin https://oauth2:${OAUTH_API}@${repositoryUrl}"""
                    }
                    sh """git config --global user.name \"Docker agent (using Jenkins)\" """
                    sh """git config --global user.email ${committerEmail} && echo \"Setting ${committerEmail} as Tag committer\"  """
                    sh """git tag -m \"Release tag version to ${version}\" -a ${version}  """
                    sh "git push origin ${version}"
                }
            }
        }
    }
    post {
        success{
            script {
                if (env.BRANCH_NAME == 'dev') {
                    slackSendMessage("#2aad72","*${currentBuild.currentResult}:* - *Job* ${env.JOB_NAME} build ${env.BUILD_NUMBER} \n *Duration*: ${currentBuild.durationString.minus(' and counting')} \n Release tag version: *${VERSION_NUMBER}-rc* \n More info at: <${env.BUILD_URL} | *Here* >")
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
    version = sh returnStdout: true, script: """(egrep -o "([0-9]{1,}\\.)+[0-9]{1,}" version.properties)"""
    VERSION_TRIM = version.trim()
    VERSION_NUMBER = "${VERSION_TRIM}"
}
def slackSendMessage(color, message){
    slackSend (
            channel: "#jenkinsbuilds",
            color: """$color""",
            message: """$message"""
    )
}

