
pipeline {
    agent any

    options {
        skipDefaultCheckout(true)
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
                bat 'git rev-parse --short HEAD'
            }
        }

        stage('Environment Check') {
            steps {
                bat 'git --version'
                bat 'docker --version'
                bat 'docker compose version'
                bat 'docker info >NUL'
            }
        }
    }

    post {
        success {
            echo 'ParkirPintar: Integrasi SCM berhasil'
        }

        failure {
            echo 'ParkirPintar: Pipeline gagal'
        }
    }
}
