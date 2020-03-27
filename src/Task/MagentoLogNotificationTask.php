<?php

namespace Glushko\GrumphpMagento2\Task;

use DateTime;
use Dubture\Monolog\Reader\LogReader;
use Exception;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\AbstractExternalTask;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
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
            'var_dir' => './var',
        ]);

        $resolver->addAllowedTypes('var_dir', ['string']);

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
        $varDir = $config['var_dir'];

        $logFiles = glob($varDir . '/*/*/*.log', GLOB_NOSORT);

        $dateNow = new DateTime();
        $report = [];

        foreach ($logFiles as $logPath) {
            $logReader = new LogReader($logPath);
            $logCount = count($logReader);

            // go from the bottom to the top of the log file
            // and count how many records are inside of report interval (like not older then 1 day)
            for ($i = $logCount - 1; $i >= 0; $i--) {
                $lastLine = $logReader[$i];

                // check log severity
                if ($lastLine['level'] === 'INFO') {
                    continue;
                }

                // calculate log relevance in hours
                $logRelevance = ($dateNow->getTimestamp() - $lastLine['date']->getTimestamp()) / ( 60 * 60 );

                // check log relevance
                if ($logRelevance > 24) {
                    break;
                }

                $report[$logPath] = $report[$logPath] ? $report[$logPath] + 1 : 1;
            }
        }

        if (0 === count($report)) {
            return TaskResult::createPassed($this, $context);
        }

        $message = '✘ Magento Logs have recently added records:' . PHP_EOL;

        $message .= array_map(function ($logPath, $countRelevantRecords) {
            return sprintf('• %s - %s records', $logPath, $countRelevantRecords) . PHP_EOL;
        }, $report);

        return TaskResult::createFailed(
            $this,
            $context,
            $message
        );
    }
}
