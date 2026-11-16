
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

                script {
                    def container = "parkirpintar-test-${env.BUILD_NUMBER}"

                    bat """
                        docker create --name ${container} -w /app -e APP_ENV=testing -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e COMPOSER_ALLOW_SUPERUSER=1 parkirpintar-test:local sh -ec "composer install --no-interaction --prefer-dist --no-progress; vendor/bin/phpunit --log-junit /tmp/junit.xml"
                    """

                    try {
                        bat 'git archive --format=tar -o ci-source.tar HEAD'

                        bat "docker cp - ${container}:/app < ci-source.tar"

                        def startCode = bat(
                            returnStatus: true,
                            script: "docker start -a ${container}"
                        )

                        def reportCode = bat(
                            returnStatus: true,
                            script: "docker cp ${container}:/tmp/junit.xml test-results\\junit.xml"
                        )

                        def exitCode = bat(
                            returnStdout: true,
                            script: "@docker inspect -f \"{{.State.ExitCode}}\" ${container}"
                        ).trim()

                        if (reportCode != 0) {
                            error('Gagal mengambil laporan JUnit XML')
                        }

                        if (startCode != 0 || exitCode != '0') {
                            error('Automated test PHPUnit gagal')
                        }
                    } finally {
                        bat(
                            returnStatus: true,
                            script: "docker rm -f ${container}"
                        )

                        bat(
                            returnStatus: true,
                            script: 'if exist ci-source.tar del /Q ci-source.tar'
                        )
                    }
                }
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
