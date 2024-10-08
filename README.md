# BlueFission Wise Shell

## Overview

The BlueFission Wise Shell is an advanced AI-driven command shell that leverages the BlueFission libraries and event-driven architecture to provide a robust, extensible, and conversational interface for system management. The Wise Shell incorporates various BlueFission components, such as ProcessManager, CommandProcessor, MemoryManager, and FileSystem, to offer a seamless experience for both system administrators and end-users.

## Features

- **Event-Driven Architecture**: Utilizes the BlueFission event system for loose coupling and dynamic execution.
- **Conversational AI**: Supports natural language processing for command interpretation and execution.
- **Async Processing**: Handles commands asynchronously for non-blocking operations.
- **Command Aliases**: Allows for easy aliasing of commands for user convenience.
- **File System Management**: Provides comprehensive file system operations through a dedicated `FileCommand` class.
- **Memory Management**: Efficient memory pooling and management using the `Mem` class.
- **Process Management**: Manages system processes with the `ProcessManager` class.
- **Dynamic Configuration**: Easily configurable components and commands.

## Installation

1. **Clone the repository**

   ```bash
   git clone https://github.com/bluefission/wise.git
   cd wise
   ```

2. **Install dependencies**

   Ensure you have PHP installed. Then, install the required dependencies using Composer:

   ```bash
   composer install
   ```

3. **Configuration**

   Set up environment variables and configuration files as needed.

## Usage

### Booting the Kernel

To initialize and boot the Wise Shell kernel, use the following script:

```php
require 'vendor/autoload.php';

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Sys\MemoryManager;
use BlueFission\Data\Sys\FileSystem;
use BlueFission\Wise\Sys\DisplayManager;
use BlueFission\Data\Storage\Storage;
use BlueFission\Async;
use BlueFission\Automata\Langauge\IInterpreter;
use BlueFission\Automata\LLM\Clients\IClient;

$kernel = new Kernel(
    new ProcessManager(),
    new CommandProcessor(new Storage(), new LLMClient()),
    new MemoryManager(),
    new FileSystem(),
    new IInterpreter(),
    new DisplayManager(),
    new Async()
);

$kernel->boot();

// Handle requests
$request = 'some command here';
$response = $kernel->handleRequest($request);
echo $response;
```

### Command Handling

The Wise Shell supports both synchronous and asynchronous command handling. To execute a command, you can use the `handleRequest` method.

### Aliases

You can register command aliases in the `CommandHandler` class. This allows you to use shorthand commands, such as `ls` for `list` and `cd` for `changeDirectory`.

## Extending the Shell

### Adding New Commands

To add new commands, extend the `CommandHandler` class and define your methods:

```php
namespace BlueFission\Wise\Res;

class NewResource extends BaseResource {
    protected $_name = 'newResource';
    protected $_actions = ['get', 'help'];

    public function get($args) {
        // Command logic here...
        $this->_response = "Command response here";
    }
}
```

## Contributing

We welcome contributions from the community. Please fork the repository and submit pull requests for any features, enhancements, or bug fixes.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contact

For any inquiries or support, please contact BlueFission at [info@bluefission.com](mailto:info@bluefission.com).