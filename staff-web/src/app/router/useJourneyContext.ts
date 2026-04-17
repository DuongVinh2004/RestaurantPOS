import { useEffect, useMemo } from 'react';
import { useLocation } from 'react-router-dom';
import { readJourneyContext } from './journey';
import { useFlowStore } from '../store/flow-store';

export function useJourneyContext() {
  const location = useLocation();
  const applyJourney = useFlowStore((state) => state.applyJourney);
  const journey = useMemo(() => readJourneyContext(location.search), [location.search]);

  useEffect(() => {
    applyJourney(journey);
  }, [applyJourney, journey]);

  return journey;
}
