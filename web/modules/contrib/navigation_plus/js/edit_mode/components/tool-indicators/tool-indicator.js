import { h } from 'preact';
import { useSelector } from 'react-redux';
import { getIndicatorConfigs } from './tool-indicator-registry';

const Indicator = ({ refType, wrapperRef }) => {

  const currentTool = useSelector((state) => state.tool.currentTool);
  const showAllIndicators = useSelector((state) => state.tool.showAllIndicators);

  // Get the indicator config for a given config.
  const getIndicatorConfig = (config) => {
    if (config) {
      return config.find(ind => ind.type === refType) || null;
    }
    return null;
  };

  // Indicator config can optional define an "enabler" method that determines if
  // the indicator applies. @see layout-indicator.js
  const isEnabled = (indicatorConfig) => {
    return indicatorConfig?.enabler?.(wrapperRef) ?? true;
  }

  // Create indicators based on tool config.
  const createIndicator = (indicatorConfig) => {
    const iconSvg = indicatorConfig?.icon[refType] || null;
    const handlers = indicatorConfig?.handlers || {};
    return (
      <div className={`tool-indicator ${indicatorConfig.alwaysOn ? 'always-on' : ''}`} {...handlers}>
        {iconSvg && <div className="indicator-icon" dangerouslySetInnerHTML={{ __html: iconSvg }} />}
      </div>
    );
  };

  if (showAllIndicators || currentTool) {
    const allConfigs = getIndicatorConfigs();
    let indicators = [];
    for (const configName in allConfigs) {
      if (!showAllIndicators && currentTool && currentTool !== configName) {
        // Skip this indicator config because we aren't showing all indicators
        // and this is not the config for the current tool.
        continue;
      }
      const toolConfig = allConfigs[configName];
      const indicatorConfig = getIndicatorConfig(toolConfig);

      if (indicatorConfig && isEnabled(indicatorConfig)) {
        const indicator = createIndicator(indicatorConfig);
        indicators.push(indicator);
      }
    }

    return indicators;
  } else {
    return null;
  }
};

export default Indicator;
