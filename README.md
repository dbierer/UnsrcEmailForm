#unlikelysource.com Simple Email Form

##View Helper
USAGE (in a view template):
```
echo $this->unsrcRender([$config, $instance, $template, $action, $messages])
```

##Controller Plugin
USAGE (in a controller action):
```
$viewModel = $this->unsrcRender([$config, $instance, $template, $action, $messages])
```

##Parameters (for both the view helper and controller plugin)
###string $config
- Configuration key within "unsrc-config" to use
- Default = `default`
###int $instance
- Which instance of the form for this configuration
- You can create as many instances as you with for any given configuration
- Default = 1
###string $template
- Which template to use to render the form
- Default = "unsrc-email-form/index/index.phtml"
###string $action
- "action" attribute inside the <form> tag
- Default = ""
###string $messages
- Any messages to be displayed in the form (i.e. errors)
- Default = NULL

##Phase I
- Port from Joomla module to ZF2 module
- DONE

##Phase II
- Incorporate ZF2 form elements instead of straight HTML
- Implement file uploads

##Phase III
- Incorporate Zend\I18n\Translate
