import fs from 'fs';

const filePath = '/Users/rafael.silva/Documents/agentflix/app/src/app/pages/chat/autopilot/help/help.ts';
let content = fs.readFileSync(filePath, 'utf8');

const iconsStr = ['lucideArrowRight', 'lucideBrain', 'lucideCheckCircle', 'lucideChevronDown', 'lucideClock', 'lucideFileText', 'lucideGitBranchPlus', 'lucideHelpCircle', 'lucideHistory', 'lucideLightbulb', 'lucideLineChart', 'lucideList', 'lucideMessageSquare', 'lucidePlay', 'lucideRocket', 'lucideShield', 'lucideWorkflow', 'lucideWrench', 'lucideZap'].join(', ');

if (!content.includes('lucideBrain')) {
    content = content.replace("import { NgIcon } from '@ng-icons/core';", `import { NgIcon, provideIcons } from '@ng-icons/core';\nimport { ${iconsStr} } from '@ng-icons/lucide';`);

    content = content.replace("  schemas: [CUSTOM_ELEMENTS_SCHEMA],", `  viewProviders: [provideIcons({ ${iconsStr} })],\n  schemas: [CUSTOM_ELEMENTS_SCHEMA],`);

    fs.writeFileSync(filePath, content);
}
