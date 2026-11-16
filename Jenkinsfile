
pipeline {
    agent any

    options {
        skipDefaultCheckout(true)
        disableConcurrentBuilds()
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
                bat 'git rev-parse --short HEAD'
            }
        }

        stage('Test') {
            steps {
                bat 'docker build -f ci/Dockerfile.test -t parkirpintar-test:local ci'

                bat 'if not exist test-results mkdir test-results'
                bat 'if exist test-results\\junit.xml del /Q test-results\\junit.xml'

                bat 'docker run --rm --mount "type=bind,source=%CD%,target=/app" -w /app -e COMPOSER_ALLOW_SUPERUSER=1 parkirpintar-test:local sh -ec "composer install --no-interaction --prefer-dist --no-progress; if [ ! -f .env ]; then cp .env.example .env; fi; php artisan key:generate --force; vendor/bin/phpunit --log-junit test-results/junit.xml"'
            }
        }
    }

    post {
        always {
            script {
                if (fileExists('test-results/junit.xml')) {
                    junit(
                        testResults: 'test-results/junit.xml',
                        allowEmptyResults: false,
                        skipPublishingChecks: true
                    )
                } else {
                    echo 'Laporan JUnit belum tersedia'
                }
            }
        }

        success {
            echo 'ParkirPintar: Automated tests berhasil'
        }

        failure {
            echo 'ParkirPintar: Pipeline gagal'
        }
    }
}
