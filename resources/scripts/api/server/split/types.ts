export interface SplitResourceTotals {
    memory: number;
    disk: number;
    cpu: number;
    swap: number;
}

export interface SplitFamilyServer {
    id: number;
    uuid: string;
    identifier: string;
    name: string;
    memory: number;
    disk: number;
    cpu: number;
    swap: number;
    isRoot: boolean;
    isCurrent: boolean;
    hasChildren: boolean;
    canDelete: boolean;
    allocation: {
        ip: string | null;
        alias: string | null;
        port: number | null;
        label: string;
    };
}

export interface SplitOverview {
    splitLimit: number;
    splitsUsed: number;
    remainingSplits: number;
    canCreate: boolean;
    createBlockedReason: string | null;
    available: SplitResourceTotals;
    familyTotals: SplitResourceTotals;
    servers: SplitFamilyServer[];
}
