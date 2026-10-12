import type { IconifyIcon } from "@iconify/types";

import { iconData01 } from "./part1";
import { iconData02 } from "./part2";
import { iconData03 } from "./part3";
import { iconData04 } from "./part4";
import { iconData05 } from "./part5";
import { ICON_ALIASES } from "../aliases";

type IconModule = IconifyIcon | { default: IconifyIcon };

function normalizeIconData(value: unknown, name: string): IconifyIcon {
    const candidate =
        typeof value === "object" &&
        value !== null &&
        "default" in value
            ? (value as { default: unknown }).default
            : value;

    if (
        typeof candidate !== "object" ||
        candidate === null ||
        !("width" in candidate) ||
        !("height" in candidate) ||
        !("body" in candidate)
    ) {
        throw new Error(
            `[Icon Registry] Invalid icon data for "${name}".`,
        );
    }

    const icon = candidate as {
        width: unknown;
        height: unknown;
        body: unknown;
    };

    if (
        typeof icon.width !== "number" ||
        typeof icon.height !== "number" ||
        typeof icon.body !== "string" ||
        icon.body.trim() === ""
    ) {
        throw new Error(
            `[Icon Registry] Icon "${name}" has invalid dimensions or an empty body.`,
        );
    }

    return candidate as IconifyIcon;
}

const rawRegistry: Record<string, unknown> = Object.assign(
    {},
    iconData01,
    iconData02,
    iconData03,
    iconData04,
    iconData05,
);

const normalizedRegistry: Record<string, IconifyIcon> = {};

for (const [name, data] of Object.entries(rawRegistry)) {
    normalizedRegistry[name] = normalizeIconData(data, name);
}

export const ICON_REGISTRY: Record<string, IconifyIcon> =
    Object.freeze(normalizedRegistry);


export const ICON_NAMES: readonly string[] = Object.freeze(
    Object.keys(ICON_REGISTRY).sort(
        (a, b) => a.localeCompare(b),
    ),
);


export function normalizeIconName(value: string | null | undefined): string {
    if (typeof value !== "string") {
        return "";
    }

    return value
        .trim()
        .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
        .replace(/([A-Z])([A-Z][a-z])/g, "$1-$2")
        .replace(/[_\s]+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "")
        .toLowerCase()
        .replace(/^tabler:/, "");
}

export function resolveIconName(value: string | null | undefined): string {
    const normalized = normalizeIconName(value);
    const aliasedName = ICON_ALIASES[normalized] ?? normalized;

    return Object.prototype.hasOwnProperty.call(
        ICON_REGISTRY,
        aliasedName,
    )
        ? aliasedName
        : "help-circle";
}

