<?php

/**
 * PHPStan stubs for the parts of aws/aws-sdk-php that the Sqs adapter touches.
 *
 * The SDK is a "suggest", not a hard requirement, so it isn't in the vendor tree
 * during analysis and PHPStan would otherwise report every SqsClient call as an
 * unknown class - which would leave src/Adapter/Sqs.php effectively unanalyzed,
 * the one adapter with no test coverage to fall back on.
 *
 * These stubs deliberately cover only the surface pop-queue actually uses. The
 * real methods are magic (Aws\AwsClient::__call, declared via @method tags), so
 * there is nothing more concrete upstream to point PHPStan at.
 *
 * @see https://phpstan.org/user-guide/stub-files
 */

namespace Aws {

    class Result
    {
        /**
         * @param  string $key
         * @return mixed
         */
        public function get($key)
        {
        }
    }

}

namespace Aws\Sqs {

    class SqsClient
    {
        /**
         * @param array<string, mixed> $args
         */
        public function getQueueAttributes(array $args = []): \Aws\Result
        {
        }

        /**
         * @param array<string, mixed> $args
         */
        public function sendMessage(array $args = []): \Aws\Result
        {
        }

        /**
         * @param array<string, mixed> $args
         */
        public function receiveMessage(array $args = []): \Aws\Result
        {
        }

        /**
         * @param array<string, mixed> $args
         */
        public function deleteMessage(array $args = []): \Aws\Result
        {
        }

        /**
         * @param array<string, mixed> $args
         */
        public function purgeQueue(array $args = []): \Aws\Result
        {
        }
    }

}
