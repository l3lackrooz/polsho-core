<?php

namespace App\Domain\Market\Application\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Domain\Market\Actions\CreateMarketProvider;

use function Laravel\Prompts\{
    text,
    select,
    confirm,
    info,
    warning,
    error
};

class MakeMarketProviderCommand extends Command
{
    protected $signature = 'market:provider:add';
    protected $description = 'Nuxt-style wizard for creating a Market Provider';

    protected array $data = [];
    protected int $step = 1;
    protected int $maxSteps = 7;

    public function handle(): int
    {
        $this->welcomeHeader();

        while (true) {
            match ($this->step) {
                1 => $this->stepName(),
                2 => $this->stepSlug(),
                3 => $this->stepDescription(),
                4 => $this->stepClassName(),
                5 => $this->stepPriority(),
                6 => $this->stepStatus(),
                7 => $this->stepConfig(),
            };

            if ($this->step > $this->maxSteps) {
                if ($this->finish()) {
                    return self::SUCCESS;
                }
                $this->step = 1;
            }
        }
    }

    private function welcomeHeader(): void
    {
        $ascii = <<<TXT
█████  █████  █      █████  █   █  █████
█   █  █   █  █      █      █   █  █   █
█████  █   █  █      █████  █████  █   █
█      █   █  █          █  █   █  █   █
█      █████  █████  █████  █   █  █████
             P  O  L  S  H  O
TXT;

        info($ascii);
    }

    private function stepHeader(string $title): void
    {
        info("◆  {$title}");
    }

    private function nextStep(): void
    {
        $action = select(
            label: "Next step?",
            options: [
                'next' => 'Continue',
                'back' => 'Go back',
                'exit' => 'Exit Wizard'
            ],
            default: 'next'
        );

        match ($action) {
            'next' => $this->step++,
            'back' => $this->step > 1 ? $this->step-- : $this->step,
            'exit' => exit(warning("Wizard cancelled.")),
        };
    }

    /* --------------------------------------------------------------- */
    /*  Step Handlers                                                  */
    /* --------------------------------------------------------------- */

    private function stepName(): void
    {
        $this->stepHeader("Provider Name");

        $this->data['name'] = text(
            label: "○ Enter provider name:",
            required: "Name is required."
        );

        $this->nextStep();
    }

    private function stepSlug(): void
    {
        $this->stepHeader("Provider Slug");

        $this->data['slug'] = text(
            label: "○ Slug (leave empty to auto-generate):",
            default: Str::slug($this->data['name'])
        );

        $this->nextStep();
    }

    private function stepDescription(): void
    {
        $this->stepHeader("Description");

        $this->data['description'] = text(
            label: "○ Short description:",
            required: false
        );

        $this->nextStep();
    }

    private function stepClassName(): void
    {
        $this->stepHeader("Class Namespace");

        // Scan filesystem for *Driver.php classes
        $driverClasses = $this->scanDriverClasses();
        $providerFolder = Str::studly($this->data['name']);

        // Construct expected class
        $expected = "App\\Domain\\Market\\Infrastructure\\Providers\\{$providerFolder}\\{$providerFolder}Driver";

        $choices = [];

        // Suggested option
        if (!in_array($expected, $driverClasses)) {
            $choices[$expected] = "● Create new: {$expected}";
        }

        // Existing driver classes
        foreach ($driverClasses as $class) {
            $choices[$class] = "○ Existing: {$class}";
        }

        // Ask user
        $this->data['class_name'] = select(
            label: "◆ Choose or create driver class",
            options: $choices
        );

        $this->nextStep();
    }

    private function scanDriverClasses(): array
    {
        $path = base_path("app/Domain/Market/Application/Providers");
        if (!File::exists($path)) {
            return [];
        }

        $classes = [];

        foreach (File::allFiles($path) as $file) {
            if (str_ends_with($file->getFilename(), "Driver.php")) {

                // Convert file path → namespace
                $relative = str_replace(
                    [base_path() . "/", "/", ".php"],
                    ["", "\\", ""],
                    $file->getRealPath()
                );

                $classes[] = $relative; // Fully-qualified namespace
            }
        }

        return $classes;
    }

    private function stepPriority(): void
    {
        $this->stepHeader("Priority");

        $priority = text(
            label: "○ Priority (integer):",
            default: "1",
            validate: fn($v) => is_numeric($v) ? null : "Must be numeric."
        );

        $this->data['priority'] = (int)$priority;
        $this->data['is_default'] = confirm("○ Set as default provider?", false);

        $this->nextStep();
    }

    private function stepStatus(): void
    {
        $this->stepHeader("Status");

        $this->data['status'] = select(
            label: "◆ Choose provider status",
            options: [
                "active" => "● active",
                "inactive" => "○ inactive",
            ],
            default: "active"
        );

        $this->nextStep();
    }

    private function stepConfig(): void
    {
        $this->stepHeader("Provider Config");

        info("○ Example JSON: {\"base_url\": \"https://api.binance.com\"}");

        $value = text(
            label: "Enter config JSON (optional):",
            required: false,
            validate: function ($input) {
                if (!$input) return null;
                return json_decode($input, true) !== null
                    ? null
                    : "Invalid JSON format.";
            }
        );

        $this->data['config'] = $value ? json_decode($value, true) : null;

        $this->step++;
    }

    /* --------------------------------------------------------------- */
    /*  Final Summary                                                  */
    /* --------------------------------------------------------------- */

    private function finish(): bool
    {
        info("\n┌  Summary");
        foreach ($this->data as $k => $v) {
            $formatted = is_array($v) ? json_encode($v, JSON_PRETTY_PRINT) : $v;
            info("│  {$k}: {$formatted}");
        }
        info("└");

        if (!confirm("Save provider?", true)) {
            warning("Restarting wizard...");
            return false;
        }

        app(CreateMarketProvider::class)->execute($this->data);

        info("✔ Provider successfully created");

        return true;
    }
}
