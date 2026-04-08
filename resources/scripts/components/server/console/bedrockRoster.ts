export const BEDROCK_SYSTEM_LIST_EVENT = 'server-console:bedrock-system-list';

export type BedrockConsoleRoster = {
    online: number;
    max: number;
    names: string[];
    observedAt: number;
};

export const normalizeConsoleLine = (line: string): string =>
    line.replace(/\x1B\[[0-9;]*[A-Za-z]/g, '').trim();

export const isBedrockListCommandEcho = (line: string): boolean => /^list$/i.test(normalizeConsoleLine(line));

const splitBedrockRosterNames = (value: string): string[] => {
    const normalized = normalizeConsoleLine(value);
    if (
        !normalized ||
        /^\[.*\]/.test(normalized) ||
        /^list$/i.test(normalized) ||
        /^there are\s+\d+\s*\/\s*\d+\s+players online:/i.test(normalized)
    ) {
        return [];
    }

    const seen = new Set<string>();

    return normalized
        .split(',')
        .map((entry) => entry.trim())
        .filter((entry) => {
            if (!entry) {
                return false;
            }

            const key = entry.toLowerCase();
            if (seen.has(key)) {
                return false;
            }

            seen.add(key);
            return true;
        });
};

export const parseBedrockRosterHeader = (
    line: string
): { online: number; max: number; names: string[] } | null => {
    const normalized = normalizeConsoleLine(line);
    const match = normalized.match(/There are\s+(\d+)\s*\/\s*(\d+)\s+players online:\s*(.*)$/i);

    if (!match) {
        return null;
    }

    return {
        online: Number.parseInt(match[1], 10) || 0,
        max: Number.parseInt(match[2], 10) || 0,
        names: splitBedrockRosterNames(match[3] || ''),
    };
};

export const parseBedrockRosterNames = (line: string): string[] => splitBedrockRosterNames(line);
