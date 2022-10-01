<?php

declare(strict_types=1);

namespace RafaelYon\PhpInsightsReviewer\Clients;

use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GithubClient
{
    public const REVIEW_COMMENT_LEFT_SIDE = 'LEFT';
    public const REVIEW_COMMENT_RIGHT_SIDE = 'RIGHT';

    public const REVIEW_EVENT_ACTION_APPROVE = 'APPROVE';
    public const REVIEW_EVENT_ACTION_COMMENT = 'COMMENT';
    public const REVIEW_EVENT_ACTION_REQUEST_CHANGES = 'REQUEST_CHANGES';

    private const USER_AGENT = 'PhpInsightsReviewer/1.0 (symfony/http-client)';

    private HttpClientInterface $client;

    public function __construct(
        string $apiUrl,
        string $apiBearerToken
    ) {
        $this->client = HttpClient::createForBaseUri(
            $apiUrl,
            [
                'auth_bearer' => $apiBearerToken,
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                ],
            ]
        );
    }

    /**
     * Create a review comment for pull request (PR).
     *
     * @see https://docs.github.com/en/rest/pulls/comments#create-a-review-comment-for-a-pull-request
     *
     * @throws Exception
     */
    public function createPullRequestReviewComment(
        string $fullRepositoryName,
        int $pullRequestNumber,
        string $commitId,
        string $comment,
        string $filePath,
        int $line,
        string $side,
        ?int $startLine = null,
        ?string $startSide = null,
        int $timeout = 10
    ): void {
        $body = [
            'body' => $comment,
            'commit_id' => $commitId,
            'path' => $filePath,
            'line' => $line,
            'side' => $side,
        ];

        if ($startLine !== null && $startSide !== null) {
            $body['start_line'] = $startLine;
            $body['start_side'] = $startSide;
        }

        $this->request(
            $timeout,
            'POST',
            "repos/{$fullRepositoryName}/pulls/{$pullRequestNumber}/comments",
            [
                'json' => $body,
            ],
            201
        );
    }

    /**
     * Create a review for pull request (PR).
     *
     * @see https://docs.github.com/en/rest/pulls/reviews#create-a-review-for-a-pull-request
     *
     * @throws Exception
     */
    public function createPullRequestReview(
        string $fullRepositoryName,
        int $pullRequestNumber,
        string $commitId,
        string $event,
        string $body,
        int $timeout = 10
    ): void {
        $this->request(
            $timeout,
            'POST',
            "repos/{$fullRepositoryName}/pulls/{$pullRequestNumber}/reviews",
            [
                'json' => [
                    'commit_id' => $commitId,
                    'event' => $event,
                    'body' => $body,
                ],
            ]
        );
    }

    /**
     * @throws Exception
     */
    private function request(
        int $timeout,
        string $method,
        string $url,
        array $options = [],
        int $expectedStatusCode = 200
    ): ResponseInterface {
        $options['timeout'] = $timeout;

        if (! isset($options['headers'])) {
            $options['headers'] = [];
        }

        if (! isset($options['headers']['Accept'])) {
            $options['headers']['Accept'] = 'application/vnd.github+json';
        }

        $response = $this->client->request($method, $url, $options);

        if ($response->getStatusCode() !== $expectedStatusCode) {
            throw new Exception(
                'Can\'t create pull request comment. GitHub return ['
                . $response->getStatusCode()
                . '] "'
                . $response->getContent(false)
                . '".'
            );
        }

        return $response;
    }
}