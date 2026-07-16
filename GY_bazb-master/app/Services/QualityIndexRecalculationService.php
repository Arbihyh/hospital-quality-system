<?php

namespace App\Services;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * 质量指标重新计算调度服务。
 *
 * 独立指标优先调用 ZhiBiao 目录中的 Artisan 命令，病案管理指标统一调用
 * command:zb_bagl，避免控制器维护大量重复的指标分支。
 */
class QualityIndexRecalculationService
{
    /**
     * 病案管理指标，由 command:zb_bagl 统一调度。
     */
    private const MEDICAL_RECORD_INDICATORS = [
        'basy24',
        'bhlfzbl',
        'bljcjl',
        'cdl',
        'ctmrfhl',
        'cyjl24',
        'exzlfl',
        'exzlhxzl',
        'gdwzl',
        'hzqjcgl',
        'hzqjjsl',
        'jjbll',
        'kjywsy',
        'lcyx',
        'ryjl24',
        'ssjl24',
        'ssxgjl',
        'xjpyjcjl',
        'yscf',
        'zqtys',
        'zrw',
        'zyssbmzql',
        'zysszql',
        'zyzdbmzql',
        'zyzdzql',
    ];

    /**
     * 指标编码与历史 Artisan 命令名称不一致时的兼容映射。
     */
    private const COMMAND_ALIASES = [
        'lcyxqpgjl' => 'zb_lcyxqpg',
        'qtzdzql4' => 'zb_qtzdzql',
        'ryjlxswcl24' => 'zb_ryjl24wcl',
        'scbcjlwc8' => 'zb_scbc8wcl',
        'sijssbfz' => 'zb_sijssbfzfsl',
        'sjssbfz' => 'zb_sjssbfzfsl',
        'sjssysjssbfzfs' => 'zb_sjssysjssbfzfsl',
        'sjssysjssswlb' => 'zb_sjssysjsshzswl',
    ];

    /**
     * 独立指标命令所在的命名空间。
     */
    private const INDICATOR_COMMAND_NAMESPACE = 'App\\Console\\Commands\\ZhiBiao\\';

    /**
     * @var ConsoleKernel
     */
    private $consoleKernel;

    /**
     * @param ConsoleKernel $consoleKernel
     */
    public function __construct(ConsoleKernel $consoleKernel)
    {
        $this->consoleKernel = $consoleKernel;
    }

    /**
     * 执行指定指标的重新计算。
     *
     * @param string $indexName
     * @param string|null $inpatientNumber
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    public function recalculate(
        string $indexName,
        ?string $inpatientNumber,
        string $startTime,
        string $endTime
    ): array {
        if (in_array($indexName, self::MEDICAL_RECORD_INDICATORS, true)) {
            return $this->executeMedicalRecordIndicator(
                $indexName,
                $inpatientNumber,
                $startTime,
                $endTime
            );
        }

        $commandName = self::COMMAND_ALIASES[$indexName] ?? 'zb_' . $indexName;
        $command = $this->resolveIndicatorCommand($commandName);
        if ($command === null) {
            return [
                'supported' => false,
                'handler' => null,
                'command' => null,
            ];
        }

        $commandArguments = $this->buildIndicatorCommandArguments(
            $command,
            $inpatientNumber,
            $startTime,
            $endTime
        );

        $this->executeCommand($commandName, $commandArguments);

        return [
            'supported' => true,
            'handler' => 'indicator_command',
            'command' => $commandName,
        ];
    }

    /**
     * 调用病案管理指标统一命令。
     *
     * @param string $indexName
     * @param string|null $inpatientNumber
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function executeMedicalRecordIndicator(
        string $indexName,
        ?string $inpatientNumber,
        string $startTime,
        string $endTime
    ): array {
        $commandArguments = [
            'type' => $indexName,
            'start' => $startTime,
            'end' => $endTime,
        ];

        if ($this->hasValue($inpatientNumber)) {
            $commandArguments['zyh'] = $inpatientNumber;
        }

        $this->executeCommand('command:zb_bagl', $commandArguments);

        return [
            'supported' => true,
            'handler' => 'medical_record_command',
            'command' => 'command:zb_bagl',
        ];
    }

    /**
     * 仅允许调度 ZhiBiao 目录中的指标命令。
     *
     * @param string $commandName
     * @return Command|null
     */
    private function resolveIndicatorCommand(string $commandName): ?Command
    {
        $registeredCommands = $this->consoleKernel->all();
        $command = $registeredCommands[$commandName] ?? null;

        if (!$command instanceof Command) {
            return null;
        }

        $commandClass = get_class($command);
        if (strpos($commandClass, self::INDICATOR_COMMAND_NAMESPACE) !== 0) {
            return null;
        }

        return $command;
    }

    /**
     * 根据命令真实签名构建参数，兼容历史参数命名差异。
     *
     * @param Command $command
     * @param string|null $inpatientNumber
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function buildIndicatorCommandArguments(
        Command $command,
        ?string $inpatientNumber,
        string $startTime,
        string $endTime
    ): array {
        $definition = $command->getDefinition();
        $commandArguments = [];

        if ($definition->hasArgument('zyh') && $this->hasValue($inpatientNumber)) {
            $commandArguments['zyh'] = $inpatientNumber;
        }

        $startArgumentName = $this->resolveArgumentName(
            $command,
            ['startTime', 'start_time', 'start']
        );
        if ($startArgumentName !== null) {
            $commandArguments[$startArgumentName] = $startTime;
        }

        $endArgumentName = $this->resolveArgumentName(
            $command,
            ['endTime', 'end_time', 'end']
        );
        if ($endArgumentName !== null) {
            $commandArguments[$endArgumentName] = $endTime;
        }

        return $commandArguments;
    }

    /**
     * 从候选参数名中查找命令实际支持的参数。
     *
     * @param Command $command
     * @param array $candidateNames
     * @return string|null
     */
    private function resolveArgumentName(Command $command, array $candidateNames): ?string
    {
        $definition = $command->getDefinition();

        foreach ($candidateNames as $candidateName) {
            if ($definition->hasArgument($candidateName)) {
                return $candidateName;
            }
        }

        return null;
    }

    /**
     * 执行 Artisan 命令并校验退出码。
     *
     * @param string $commandName
     * @param array $arguments
     * @return void
     */
    private function executeCommand(string $commandName, array $arguments): void
    {
        $commandOutputBuffer = new BufferedOutput();
        $initialOutputBufferLevel = ob_get_level();
        $nativeOutput = '';

        ob_start();

        try {
            $exitCode = $this->consoleKernel->call(
                $commandName,
                $arguments,
                $commandOutputBuffer
            );
            $nativeOutput = (string) ob_get_contents();
        } finally {
            while (ob_get_level() > $initialOutputBufferLevel) {
                ob_end_clean();
            }
        }

        if ($exitCode === 0) {
            return;
        }

        $commandOutput = trim(
            $commandOutputBuffer->fetch() . PHP_EOL . $nativeOutput
        );
        throw new RuntimeException(sprintf(
            '指标命令执行失败，命令：%s，退出码：%d，输出：%s',
            $commandName,
            $exitCode,
            $commandOutput
        ));
    }

    /**
     * 判断可选字符串参数是否包含有效值。
     *
     * @param string|null $value
     * @return bool
     */
    private function hasValue(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
