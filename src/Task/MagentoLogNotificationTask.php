<?php

namespace Glushko\GrumphpMagento2\Task;

use DateTime;
use Exception;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\AbstractExternalTask;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use MonologParser\Reader\LogReader;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * MagentoLogNotificationTask task
 */
class MagentoLogNotificationTask extends AbstractExternalTask
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'magento2-log-notification';
    }

    /**
     * @return OptionsResolver
     */
    public function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();

        $resolver->setDefaults([
            'log_patterns' => './var/*/*.log',
        ]);

        $resolver->addAllowedTypes('log_patterns', ['array']);

        return $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function canRunInContext(ContextInterface $context): bool
    {
        return ($context instanceof GitPreCommitContext || $context instanceof RunContext);
    }

    /**
     * Notify about recently added records in Magento logs
     *
     * @param ContextInterface $context
     *
     * @return TaskResultInterface
     *
     * @throws Exception
     */
    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfiguration();
        $logPatterns = $config['log_patterns'];

        $logFiles = [];

        foreach ($logPatterns as $logPattern) {
            $logFiles[] = glob($logPattern, GLOB_NOSORT);
        }

        $logFiles = array_merge(...$logFiles);

        $dateNow = new DateTime();
        $logReport = [];

        foreach ($logFiles as $logPath) {
            $logReader = new LogReader($logPath);
            $logCount = count($logReader);

            // go from the bottom to the top of the log file
            // and count how many records are inside of report interval (like not older then 1 day)
            for ($i = $logCount - 1; $i >= 0; $i--) {
                $lastLine = $logReader[$i];

                // calculate log relevance in hours
                $recordFreshness = $dateNow->diff($lastLine['date'])->days;

                // check log relevance
                if ($recordFreshness > 10) {
                    break;
                }

                // check log severity
                if ($lastLine['level'] === 'INFO') {
                    continue;
                }

                $logReport[$logPath] = array_key_exists($logPath, $logReport) ? $logReport[$logPath] + 1 : 1;
            }
        }

        if (0 === count($logReport)) {
            return TaskResult::createPassed($this, $context);
        }

        $message = '✘ Magento Logs have recently added records:' . PHP_EOL;

        foreach ($logReport as $logPath => $recentLogCount) {
            $message .= sprintf(
                    '• %s - %s %s',
                    $logPath,
                    $recentLogCount,
                    $recentLogCount > 1 ? 'records' : 'record'
                ) . PHP_EOL;
        }

        return TaskResult::createFailed(
            $this,
            $context,
            $message
        );
    }
}
