$ErrorActionPreference = "Stop"

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$sourceRoot = Join-Path (Split-Path -Parent $workspaceRoot) "portfolio-source-images"
$destinationRoot = Join-Path $workspaceRoot "public/images/projects"
$dataFile = Join-Path $workspaceRoot "src/data/projects.ts"

$projectDefinitions = @(
  @{ Source = "Editorial/1_Rainbow"; Slug = "rainbow"; Category = "editorial"; De = "Rainbow"; En = "Rainbow" },
  @{ Source = "Editorial/2_Renaissance"; Slug = "renaissance"; Category = "editorial"; De = "Renaissance"; En = "Renaissance" },
  @{ Source = "Editorial/3_Many Facets"; Slug = "many-facets"; Category = "editorial"; De = "Many Facets"; En = "Many Facets" },
  @{ Source = "Editorial/4_Easy Morning"; Slug = "easy-morning"; Category = "editorial"; De = "Easy Morning"; En = "Easy Morning" },
  @{ Source = "Editorial/5_SheBloomsinBlue"; Slug = "she-blooms-in-blue"; Category = "editorial"; De = "She Blooms in Blue"; En = "She Blooms in Blue" },
  @{ Source = "Events/01_ABOUT POP"; Slug = "about-pop"; Category = "events"; De = "ABOUT POP"; En = "ABOUT POP" },
  @{ Source = "Events/02_Personio 2.0"; Slug = "personio-2"; Category = "events"; De = "Personio 2.0"; En = "Personio 2.0" },
  @{ Source = "Events/03_Partscloud"; Slug = "partscloud"; Category = "events"; De = "PartsCloud"; En = "PartsCloud" },
  @{ Source = "Events/04_Porsche Postcards"; Slug = "porsche-postcards"; Category = "events"; De = "Porsche Postcards"; En = "Porsche Postcards" },
  @{ Source = "Events/05_Sicherheitspolitischer Dialog zu Sicherheit und Verteidigung"; Slug = "sicherheitspolitischer-dialog"; Category = "events"; De = "Sicherheitspolitischer Dialog"; En = "Security Policy Dialogue" },
  @{ Source = "Hochzeiten/01_Laura & Micha"; Slug = "laura-micha"; Category = "weddings"; De = "Laura & Micha"; En = "Laura & Micha" },
  @{ Source = "Hochzeiten/02_Simon & Lena"; Slug = "simon-lena"; Category = "weddings"; De = "Simon & Lena"; En = "Simon & Lena" },
  @{ Source = "Hochzeiten/03_Joko&Erik"; Slug = "joko-erik"; Category = "weddings"; De = "Joko & Erik"; En = "Joko & Erik" },
  @{ Source = "Hochzeiten/04_Johanna & Karle"; Slug = "johanna-karle"; Category = "weddings"; De = "Johanna & Karle"; En = "Johanna & Karle" },
  @{ Source = "Landschaft/Eibsee"; Slug = "eibsee"; Category = "landscape"; De = "Eibsee"; En = "Eibsee" },
  @{ Source = "Landschaft/Norwegen _ Schweden"; Slug = "norway-sweden"; Category = "landscape"; De = "Norwegen & Schweden"; En = "Norway & Sweden" }
)

function Convert-ToSafeName {
  param([string]$Name)

  $normalized = $Name.Normalize([Text.NormalizationForm]::FormD)
  $builder = New-Object Text.StringBuilder

  foreach ($character in $normalized.ToCharArray()) {
    $category = [Globalization.CharUnicodeInfo]::GetUnicodeCategory($character)
    if ($category -ne [Globalization.UnicodeCategory]::NonSpacingMark) {
      [void]$builder.Append($character)
    }
  }

  $safe = $builder.ToString().Normalize([Text.NormalizationForm]::FormC).ToLowerInvariant()
  $safe = $safe -replace "[^a-z0-9]+", "-"
  return $safe.Trim("-")
}

function Get-ImageOrder {
  param([string]$BaseName)

  if ($BaseName -match "^(\d+)(?:[._-](\d+))?") {
    $major = [int]$Matches[1]
    $minor = if ($Matches[2]) { [int]$Matches[2] } else { 0 }
    return ($major * 100) + $minor
  }

  if ($BaseName -match "(\d+)$") {
    return [int]$Matches[1] * 100
  }

  return 999900
}

New-Item -ItemType Directory -Force -Path $destinationRoot | Out-Null
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $dataFile) | Out-Null

$projectOutput = @()

foreach ($definition in $projectDefinitions) {
  $sourceDirectory = Join-Path $sourceRoot $definition.Source
  $destinationDirectory = Join-Path $destinationRoot $definition.Slug
  New-Item -ItemType Directory -Force -Path $destinationDirectory | Out-Null

  $files = Get-ChildItem -LiteralPath $sourceDirectory -File |
    Where-Object { $_.Extension.ToLowerInvariant() -in @(".jpg", ".jpeg", ".png", ".webp") } |
    Sort-Object @{ Expression = { Get-ImageOrder $_.BaseName } }, Name

  if ($files.Count -eq 0) {
    throw "No images found in $sourceDirectory"
  }

  $coverFile = $files | Where-Object { $_.BaseName -match "^0(?:_|-)" } | Select-Object -First 1
  if (-not $coverFile) {
    $coverFile = $files | Select-Object -First 1
  }

  $usedNames = @{}
  $imageRecords = @()
  $coverPath = $null

  foreach ($file in $files) {
    $orderValue = Get-ImageOrder $file.BaseName
    $safeBaseName = Convert-ToSafeName $file.BaseName
    $extension = $file.Extension.ToLowerInvariant()
    $candidateName = "$safeBaseName$extension"

    if ($usedNames.ContainsKey($candidateName)) {
      $usedNames[$candidateName] += 1
      $candidateName = "$safeBaseName-$($usedNames[$candidateName])$extension"
    } else {
      $usedNames[$candidateName] = 1
    }

    Copy-Item -LiteralPath $file.FullName -Destination (Join-Path $destinationDirectory $candidateName) -Force
    $publicPath = "/images/projects/$($definition.Slug)/$candidateName"

    if ($file.FullName -eq $coverFile.FullName) {
      $coverPath = $publicPath
      continue
    }

    $majorOrder = [math]::Floor($orderValue / 100)
    $side = if (($majorOrder % 2) -eq 0) { "right" } else { "left" }
    $imageRecords += [ordered]@{
      src = $publicPath
      altDe = "$($definition.De) – Fotografie von Madlen Medvedovskyy"
      altEn = "$($definition.En) — photography by Madlen Medvedovskyy"
      order = $orderValue
      side = $side
    }
  }

  $descriptions = switch ($definition.Category) {
    "editorial" {
      @{ De = "Editoriale Fotografie mit einem filmischen Blick für Farbe, Form und Persönlichkeit."; En = "Editorial photography with a cinematic eye for colour, form and personality." }
    }
    "events" {
      @{ De = "Eine atmosphärische Eventreportage mit Blick für Menschen, Details und echte Momente."; En = "An atmospheric event story focused on people, details and genuine moments." }
    }
    "weddings" {
      @{ De = "Eine persönliche Hochzeitsreportage, ruhig beobachtet und emotional erzählt."; En = "A personal wedding story, quietly observed and emotionally told." }
    }
    default {
      @{ De = "Landschaftsfotografie zwischen Ruhe, Weite und natürlichem Licht."; En = "Landscape photography shaped by stillness, space and natural light." }
    }
  }

  $projectOutput += [ordered]@{
    slug = $definition.Slug
    title = [ordered]@{ de = $definition.De; en = $definition.En }
    category = $definition.Category
    cover = $coverPath
    description = [ordered]@{ de = $descriptions.De; en = $descriptions.En }
    images = $imageRecords
  }
}

$json = $projectOutput | ConvertTo-Json -Depth 8
$typescript = @"
export type ProjectCategory = "editorial" | "events" | "weddings" | "landscape";
export type GallerySide = "left" | "right";

export interface LocalizedText {
  de: string;
  en: string;
}

export interface ProjectImage {
  src: string;
  altDe: string;
  altEn: string;
  order: number;
  side: GallerySide;
}

export interface Project {
  slug: string;
  title: LocalizedText;
  category: ProjectCategory;
  cover: string;
  description: LocalizedText;
  images: ProjectImage[];
}

export const projects: Project[] = $json;

export function getProjectBySlug(slug: string) {
  return projects.find((project) => project.slug === slug);
}

export function getAdjacentProjects(slug: string) {
  const index = projects.findIndex((project) => project.slug === slug);
  if (index < 0) return { previous: undefined, next: undefined };

  return {
    previous: projects[(index - 1 + projects.length) % projects.length],
    next: projects[(index + 1) % projects.length],
  };
}
"@

Set-Content -LiteralPath $dataFile -Value $typescript -Encoding UTF8
Write-Output "Generated $($projectOutput.Count) projects in $dataFile"
