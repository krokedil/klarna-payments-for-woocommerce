import { Reporter, TestCase, TestResult } from "@playwright/test/types/testReporter";
import { IncomingWebhook } from "@slack/webhook";
import { Block } from '@slack/types';

class SlackReporter implements Reporter {
    url: string
    webhook: IncomingWebhook
    testResults: { test: TestCase, result: TestResult }[];

    constructor() {
        this.url = process.env.SLACK_WEBHOOK_URL ?? '';
        this.webhook = new IncomingWebhook(this.url);
        this.testResults = [];
    }

    onTestEnd(test: TestCase, result: TestResult): void {
        this.testResults.push({ test, result });
    }

    async onEnd(result) {
        if (this.url === '') {
            return;
        }

        const blocks = this.buildMessage();

        try {
            await this.webhook.send({ blocks: blocks });
        } catch (error) {
        }
    }

    /**
     * Builds the message to send to Slack from the test results.
     *
     * @returns The message to send to Slack.
     */
    buildMessage(): Block[] {
        const blocks = [];

        // Add the header block.
        blocks.push({
            type: 'header',
            text: {
                type: 'plain_text',
                text: `Test result from ${process.env.PLUGIN_NAME}`,
                emoji: true,
            },
        });

        // Add a divider block.
        blocks.push({
            type: 'divider',
        });

        // Add plugin name, version and branch block.
        blocks.push({
            type: 'section',
            text: {
                type: 'mrkdwn',
                text: `*Plugin:* ${process.env.PLUGIN_NAME} ${process.env.PLUGIN_VERSION} (ref: ${process.env.GITHUB_REF ?? ''})`,
            },
        });

        // Add WooCommerce, WordPress and PHP version block.
        blocks.push({
            type: 'section',
            text: {
                type: 'mrkdwn',
                text: `*WooCommerce:* ${process.env.WC_VERSION}\n*WordPress:* ${process.env.WP_VERSION}\n*PHP:* ${process.env.PHP_VERSION}`,
            },
        });

        // Add a divider block.
        blocks.push({
            type: 'divider',
        });

        // Add the test results block.
        blocks.push({
            type: 'section',
            text: {
                type: 'mrkdwn',
                text: `*Tests:*\n${this.getTestsFromResult()}`,
            }
        });

        // If we are on github and we have a link to the workflow run, add it to the message.
        if (process.env.GITHUB_RUN_ID && process.env.GITHUB_SERVER_URL && process.env.GITHUB_REPOSITORY) {
            const workflowUrl = `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`;
            blocks.push({
                type: 'divider',
            });

            blocks.push({
                type: 'section',
                text: {
                    type: 'mrkdwn',
                    text: `*Workflow run:* ${workflowUrl}`,
                }
            });
        }

        return blocks;
    };

    getTestsFromResult() {
        let text = '';
        this.testResults.map((testResult) => {
            const { test, result } = testResult;
            const emoji = this.getStatusEmoji(result.status, test.expectedStatus);
            text += `${emoji} ${this.getTestNameWithParent(test)} (${result.duration}ms)\n`;
        });

        return text;
    }

    getStatusEmoji(status: string, expectedStatus: string) {
        // If the expected status is the same as the status, it means the test passed.
        if (status === expectedStatus) {
            return ':large_green_circle:';
        }

        switch (status) {
            case 'passed':
                return ':large_green_circle:';
            case 'failed':
                return ':red_circle:';
            case 'timedOut':
                return ':stopwatch:';
            case 'skipped':
                return ':large_yellow_circle:';
            default:
                return ':question:';
        }
    }

    getTestNameWithParent(test: TestCase) {
        let name = test.title;
        if (test.parent) {
            name = name.charAt(0).toLowerCase() + name.slice(1);
            name = `${test.parent.title} ${name}`;
        }

        return name;
    }
}

export default SlackReporter;
